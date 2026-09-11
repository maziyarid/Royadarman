<?php

namespace App\Policies;

use App\Domain\Identity\Enums\UserRole;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PatientCasePolicy
{
    public function view(User $user, PatientCase $case): bool
    {
        return match ($user->role) {
            UserRole::Patient => (int) $case->patient_user_id === (int) $user->id,
            UserRole::Coordinator => DB::table('case_assignments')->where('case_id', $case->id)->where('assignee_user_id', $user->id)->where('purpose', 'coordination')->whereNull('released_at')->exists(),
            UserRole::Clinician => $this->activeClinicalAssignment($user, $case),
            UserRole::ClinicRepresentative => DB::table('referral_grants')
                ->join('clinic_memberships', 'clinic_memberships.clinic_id', '=', 'referral_grants.clinic_id')
                ->leftJoin('consent_events', 'consent_events.id', '=', 'referral_grants.consent_event_id')
                ->where('referral_grants.case_id', $case->id)
                ->where('clinic_memberships.user_id', $user->id)
                ->whereNull('referral_grants.revoked_at')
                ->where(fn ($q) => $q->whereNull('referral_grants.expires_at')->orWhere('referral_grants.expires_at', '>', now()))
                ->where(fn ($q) => $q->whereNull('consent_events.revoked_at'))
                ->exists(),
            default => false,
        };
    }

    public function updateStatus(User $user, PatientCase $case): bool
    {
        return $user->role === UserRole::Coordinator && $this->view($user, $case);
    }

    private function activeClinicalAssignment(User $user, PatientCase $case): bool
    {
        return DB::table('case_assignments')->where('case_id', $case->id)->where('assignee_user_id', $user->id)->where('purpose', 'clinical_review')->whereNull('released_at')->exists()
            && DB::table('practitioners')->where('user_id', $user->id)->where('credential_status', 'verified')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists();
    }
}
