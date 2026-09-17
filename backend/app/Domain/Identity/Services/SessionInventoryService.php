<?php

namespace App\Domain\Identity\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * First-class inventory and revocation over Laravel's existing database sessions table.
 * Does not introduce a parallel session store.
 */
final class SessionInventoryService
{
    /**
     * @return Collection<int, array{id: string, ip_address: ?string, user_agent: ?string, last_activity: int, last_activity_at: string, is_current: bool, device_label: string}>
     */
    public function listForUser(User $user, ?string $currentSessionId = null): Collection
    {
        $rows = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        return $rows->map(function (object $row) use ($currentSessionId): array {
            $ua = (string) ($row->user_agent ?? '');

            return [
                'id' => (string) $row->id,
                'ip_address' => $row->ip_address !== null ? (string) $row->ip_address : null,
                'user_agent' => $ua !== '' ? $ua : null,
                'last_activity' => (int) $row->last_activity,
                'last_activity_at' => date('c', (int) $row->last_activity),
                'is_current' => $currentSessionId !== null && hash_equals((string) $row->id, $currentSessionId),
                'device_label' => $this->deviceLabel($ua),
            ];
        });
    }

    public function revokeOne(User $user, string $sessionId, ?int $actorUserId = null, string $reason = 'user_revoke_one'): bool
    {
        $deleted = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', $sessionId)
            ->delete();

        if ($deleted > 0) {
            $this->audit($actorUserId ?? $user->id, 'session.revoke_one', $user, $reason, [
                'session_id_prefix' => substr($sessionId, 0, 8),
                'deleted' => $deleted,
            ]);
        }

        return $deleted > 0;
    }

    public function revokeOthers(User $user, string $keepSessionId, ?int $actorUserId = null, string $reason = 'user_revoke_others'): int
    {
        $deleted = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $keepSessionId)
            ->delete();

        if ($deleted > 0) {
            $this->audit($actorUserId ?? $user->id, 'session.revoke_others', $user, $reason, [
                'kept_prefix' => substr($keepSessionId, 0, 8),
                'deleted' => $deleted,
            ]);
        }

        return $deleted;
    }

    public function revokeAll(User $user, ?int $actorUserId = null, string $reason = 'user_revoke_all'): int
    {
        $deleted = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();

        if ($deleted > 0) {
            $this->audit($actorUserId ?? $user->id, 'session.revoke_all', $user, $reason, [
                'deleted' => $deleted,
            ]);
        }

        return $deleted;
    }

    public function forceRevokeAll(User $subject, User $actor, string $reason = 'compromise_response'): int
    {
        $deleted = $this->revokeAll($subject, $actor->id, $reason);

        AuditEvent::query()->create([
            'actor_user_id' => $actor->id,
            'action' => 'session.force_revoke_all',
            'resource_type' => 'user',
            'resource_id' => (string) $subject->id,
            'result' => 'success',
            'reason' => $reason,
            'context' => [
                'subject_user_id' => $subject->id,
                'sessions_deleted' => $deleted,
            ],
            'correlation_id' => (string) Str::ulid(),
            'created_at' => now(),
        ]);

        return $deleted;
    }

    private function deviceLabel(string $userAgent): string
    {
        if ($userAgent === '') {
            return 'unknown';
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') && ! str_contains($userAgent, 'Chrome/') => 'Safari',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'device',
        };

        return $browser.' · '.$os;
    }

    /** @param array<string, mixed> $context */
    private function audit(int|string $actorUserId, string $action, User $subject, string $reason, array $context): void
    {
        AuditEvent::query()->create([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'resource_type' => 'user',
            'resource_id' => (string) $subject->id,
            'result' => 'success',
            'reason' => $reason,
            'context' => $context,
            'correlation_id' => (string) Str::ulid(),
            'created_at' => now(),
        ]);
    }
}
