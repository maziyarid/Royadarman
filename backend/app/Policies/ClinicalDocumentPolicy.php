<?php

namespace App\Policies;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Identity\Enums\UserRole;
use App\Models\ClinicalDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ClinicalDocumentPolicy
{
    public function upload(User $user, ClinicalDocument $document): bool
    {
        return $user->role === UserRole::Patient && (int) $document->patientCase->patient_user_id === (int) $user->id;
    }

    public function view(User $user, ClinicalDocument $document): bool
    {
        if ($document->status !== DocumentStatus::Approved) {
            return false;
        }
        if ($user->role === UserRole::Patient) {
            return (int) $document->patientCase->patient_user_id === (int) $user->id;
        }
        if ($user->role !== UserRole::Clinician) {
            return false;
        }

        return DB::table('case_assignments')->where('case_id', $document->case_id)->where('assignee_user_id', $user->id)->where('purpose', 'clinical_review')->whereNull('released_at')->exists()
            && DB::table('practitioners')->where('user_id', $user->id)->where('credential_status', 'verified')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists();
    }
}
