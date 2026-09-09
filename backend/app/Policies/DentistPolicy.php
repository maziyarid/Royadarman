<?php

namespace App\Policies;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Dentist;
use App\Models\User;

final class DentistPolicy
{
    public function view(User $user, Dentist $dentist): bool
    {
        return match ($user->role) {
            UserRole::Patient => false,
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => (int) $user->clinic_id === (int) $dentist->clinic_id,
            UserRole::Clinician => (int) $user->id === (int) $dentist->user_id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return match ($user->role) {
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => true,
            default => false,
        };
    }

    public function update(User $user, Dentist $dentist): bool
    {
        return match ($user->role) {
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => (int) $user->clinic_id === (int) $dentist->clinic_id,
            UserRole::Clinician => (int) $user->id === (int) $dentist->user_id,
            default => false,
        };
    }

    public function delete(User $user, Dentist $dentist): bool
    {
        return match ($user->role) {
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => (int) $user->clinic_id === (int) $dentist->clinic_id,
            default => false,
        };
    }
}
