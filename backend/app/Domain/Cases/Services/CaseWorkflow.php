<?php

namespace App\Domain\Cases\Services;

use App\Domain\Cases\Enums\CaseStatus;
use App\Models\AuditEvent;
use App\Models\CaseStatusEvent;
use App\Models\PatientCase;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaseWorkflow
{
    public function transition(PatientCase $case, CaseStatus $target, User $actor, ?string $reason = null, ?int $expectedVersion = null): PatientCase
    {
        return DB::transaction(function () use ($case, $target, $actor, $reason, $expectedVersion): PatientCase {
            /** @var PatientCase $locked */
            $locked = PatientCase::query()->lockForUpdate()->findOrFail($case->getKey());
            $from = $locked->status;

            if ($expectedVersion !== null && $locked->version !== $expectedVersion) {
                abort(409, 'case.version_conflict');
            }

            if (! $from->canTransitionTo($target)) {
                throw new DomainException("Invalid case transition from {$from->value} to {$target->value}.");
            }

            $correlationId = (string) Str::ulid();
            $locked->status = $target;
            $locked->version++;
            if ($target === CaseStatus::Submitted && $locked->submitted_at === null) {
                $locked->submitted_at = now();
            }
            if ($target === CaseStatus::Closed) {
                $locked->closed_at = now();
            }
            $locked->save();

            CaseStatusEvent::query()->create([
                'case_id' => $locked->id,
                'actor_user_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $target,
                'reason' => $reason,
                'correlation_id' => $correlationId,
                'created_at' => now(),
            ]);

            AuditEvent::query()->create([
                'actor_user_id' => $actor->id,
                'action' => 'case.status.transitioned',
                'resource_type' => PatientCase::class,
                'resource_id' => $locked->id,
                'result' => 'success',
                'reason' => $reason,
                'context' => ['from' => $from->value, 'to' => $target->value],
                'correlation_id' => $correlationId,
                'created_at' => now(),
            ]);

            return $locked->refresh();
        });
    }
}
