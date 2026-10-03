<?php

namespace App\Domain\Identity\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProfilePreferences
{
    /** Callers validate the two existing fields; no role, phone or credential write. */
    public function update(User $user, array $preferences, ?string $requestId): void
    {
        $preferences = array_intersect_key($preferences, array_flip(['name', 'locale']));
        if ($preferences === []) {
            return;
        }

        DB::transaction(function () use ($user, $preferences, $requestId): void {
            $user->update($preferences);
            AuditEvent::query()->create([
                'actor_user_id' => $user->id,
                'action' => 'profile.preferences.updated',
                'resource_type' => User::class,
                'resource_id' => (string) $user->id,
                'result' => 'success',
                'context' => ['fields' => array_keys($preferences), 'request_id' => $requestId],
                'correlation_id' => (string) Str::ulid(),
                'created_at' => now(),
            ]);
        });
    }
}
