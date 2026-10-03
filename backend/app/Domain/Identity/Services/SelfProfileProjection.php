<?php

namespace App\Domain\Identity\Services;

use App\Models\ClinicMembership;
use App\Models\User;

/** Own account metadata only. Membership display is never an access grant. */
final class SelfProfileProjection
{
    public function forUser(User $user): array
    {
        $now = now();
        $affiliations = $user->clinicMemberships()
            ->with('clinic:id,name')
            ->where('active_from', '<=', $now)
            ->where(fn ($query) => $query->whereNull('active_until')->orWhere('active_until', '>', $now))
            ->whereHas('clinic', fn ($query) => $query->where('is_active', true))
            ->orderBy('clinic_id')
            ->get(['clinic_id', 'membership_role', 'active_from', 'active_until'])
            ->map(fn (ClinicMembership $membership) => [
                'clinic_name' => $membership->clinic->name,
                'membership_role' => $membership->membership_role,
                'active_from' => $membership->active_from?->utc()->toIso8601String(),
                'active_until' => $membership->active_until?->utc()->toIso8601String(),
            ])->all();
        $credential = $user->practitioner()->first(['credential_status', 'verified_at', 'expires_at']);

        return [
            'id' => $user->id,
            'role' => $user->role->value,
            'locale' => $user->locale,
            'name' => $user->name,
            'clinic_affiliations' => $affiliations,
            'practitioner_credential' => $credential === null ? null : [
                'credential_status' => $credential->credential_status->value,
                'verified_at' => $credential->verified_at?->utc()->toIso8601String(),
                'expires_at' => $credential->expires_at?->utc()->toIso8601String(),
            ],
        ];
    }
}
