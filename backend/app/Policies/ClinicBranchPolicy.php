<?php

namespace App\Policies;

use App\Domain\Identity\Enums\UserRole;
use App\Models\ClinicBranch;
use App\Models\User;

final class ClinicBranchPolicy
{
    public function view(User $user, ClinicBranch $clinicBranch): bool
    {
        return match ($user->role) {
            UserRole::Patient => false,
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => (int) $user->clinic_id === (int) $clinicBranch->clinic_id,
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

    public function update(User $user, ClinicBranch $clinicBranch): bool
    {
        return match ($user->role) {
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => (int) $user->clinic_id === (int) $clinicBranch->clinic_id,
            default => false,
        };
    }

    public function delete(User $user, ClinicBranch $clinicBranch): bool
    {
        return match ($user->role) {
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => (int) $user->clinic_id === (int) $clinicBranch->clinic_id,
            default => false,
        };
    }
}
