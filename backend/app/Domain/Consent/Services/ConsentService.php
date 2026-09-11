<?php

namespace App\Domain\Consent\Services;

use App\Models\ConsentEvent;
use App\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ConsentService
{
    public function resolvePolicy(string $policyKey, string $version, string $locale): ?PolicyVersion
    {
        return PolicyVersion::query()
            ->where('policy_key', $policyKey)
            ->where('version', $version)
            ->where('locale', $locale)
            ->whereNotNull('published_at')
            ->first();
    }

    public function latestPublishedPolicy(string $policyKey, string $locale): ?PolicyVersion
    {
        return PolicyVersion::query()
            ->where('policy_key', $policyKey)
            ->where('locale', $locale)
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->first();
    }

    public function hasActiveConsent(User $subject, string $purpose, ?string $caseId = null, ?string $policyKey = null): bool
    {
        return ConsentEvent::query()
            ->where('subject_user_id', $subject->id)
            ->where('purpose', $purpose)
            ->where('decision', 'accepted')
            ->whereNull('revoked_at')
            ->when($caseId !== null, fn (Builder $q) => $q->where('case_id', $caseId))
            ->when($policyKey !== null, function (Builder $q) use ($policyKey): void {
                $q->whereHas('policyVersion', fn (Builder $pq) => $pq->where('policy_key', $policyKey));
            })
            ->exists();
    }

    public function record(
        User $subject,
        string $purpose,
        string $policyVersionId,
        string $locale,
        Request $request,
        ?string $caseId = null,
        string $decision = 'accepted',
    ): ConsentEvent {
        return ConsentEvent::query()->create([
            'subject_user_id' => $subject->id,
            'case_id' => $caseId,
            'policy_version_id' => $policyVersionId,
            'purpose' => $purpose,
            'decision' => $decision,
            'locale' => $locale,
            'channel' => 'web',
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
            'created_at' => now(),
        ]);
    }

    public function revoke(ConsentEvent $event): ConsentEvent
    {
        $event->update(['revoked_at' => now()]);

        return $event->refresh();
    }

    /**
     * Revoke every active consent event for the given subject/case/purpose and
     * immediately revoke dependent referral grants so a patient revocation cannot
     * leave an older grant authorised via an earlier consent event.
     *
     * @return array{events: list<string>, grants: list<string>}
     */
    public function revokeAllActiveFor(User $subject, string $purpose, string $caseId, ?string $policyKey = null): array
    {
        return DB::transaction(function () use ($subject, $purpose, $caseId, $policyKey): array {
            $query = ConsentEvent::query()
                ->where('subject_user_id', $subject->id)
                ->where('purpose', $purpose)
                ->where('case_id', $caseId)
                ->where('decision', 'accepted')
                ->whereNull('revoked_at')
                ->when($policyKey !== null, function (Builder $q) use ($policyKey): void {
                    $q->whereHas('policyVersion', fn (Builder $pq) => $pq->where('policy_key', $policyKey));
                })
                ->lockForUpdate();

            $eventIds = (clone $query)->pluck('id')->all();
            if ($eventIds === []) {
                return ['events' => [], 'grants' => []];
            }

            $query->update(['revoked_at' => now()]);

            // Revoke dependent referral grants linked to any of these consent
            // events so clinic access disappears immediately.
            $grantIds = DB::table('referral_grants')
                ->whereIn('consent_event_id', $eventIds)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->pluck('id')
                ->all();
            if ($grantIds !== []) {
                DB::table('referral_grants')
                    ->whereIn('id', $grantIds)
                    ->update(['revoked_at' => now(), 'updated_at' => now()]);
            }

            return ['events' => $eventIds, 'grants' => $grantIds];
        });
    }

    public function latestActiveFor(User $subject, string $purpose, ?string $caseId = null, ?string $policyKey = null): ?ConsentEvent
    {
        return ConsentEvent::query()
            ->where('subject_user_id', $subject->id)
            ->where('purpose', $purpose)
            ->where('decision', 'accepted')
            ->whereNull('revoked_at')
            ->when($caseId !== null, fn (Builder $q) => $q->where('case_id', $caseId))
            ->when($policyKey !== null, function (Builder $q) use ($policyKey): void {
                $q->whereHas('policyVersion', fn (Builder $pq) => $pq->where('policy_key', $policyKey));
            })
            ->latest('created_at')
            ->first();
    }
}
