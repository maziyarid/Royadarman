<?php

namespace App\Domain\Coordination\Services;

use App\Domain\Identity\Enums\UserRole;
use App\Models\PatientCase;
use App\Support\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CoordinatorAssignment
{
    public function assignInitial(PatientCase $case): int
    {
        $coordinator = DB::table('users')
            ->where('role', UserRole::Coordinator->value)
            ->where('is_active', true)
            ->select('users.id')
            ->selectSub(function ($query): void {
                $query->from('case_assignments as active_assignments')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('active_assignments.assignee_user_id', 'users.id')
                    ->where('active_assignments.purpose', 'coordination')
                    ->whereNull('active_assignments.released_at');
            }, 'active_case_load')
            ->orderBy('active_case_load')
            ->orderBy('users.id')
            ->lockForUpdate()
            ->first();

        if ($coordinator === null) {
            throw new DomainException(503, 'coordination.no_active_coordinator');
        }

        DB::table('case_assignments')->updateOrInsert(
            [
                'case_id' => $case->id,
                'assignee_user_id' => $coordinator->id,
                'purpose' => 'coordination',
                'released_at' => null,
            ],
            [
                'id' => (string) Str::ulid(),
                'assigned_by_user_id' => null,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $case->current_coordinator_id = $coordinator->id;
        $case->save();

        DB::table('audit_events')->insert([
            'id' => (string) Str::ulid(),
            'actor_user_id' => null,
            'action' => 'case.coordinator_auto_assigned',
            'resource_type' => PatientCase::class,
            'resource_id' => (string) $case->id,
            'result' => 'success',
            'reason' => 'least_loaded_active_coordinator',
            'context' => json_encode(['coordinator_user_id' => (int) $coordinator->id], JSON_THROW_ON_ERROR),
            'correlation_id' => (string) Str::ulid(),
            'created_at' => now(),
        ]);

        return (int) $coordinator->id;
    }
}
