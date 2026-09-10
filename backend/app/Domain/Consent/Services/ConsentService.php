<?php

namespace App\Domain\Consent\Services;

use App\Models\ConsentEvent;
use App\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
