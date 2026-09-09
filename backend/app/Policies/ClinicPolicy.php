<?php

namespace App\Policies;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Clinic;
use App\Models\User;

final class ClinicPolicy
{
    public function view(User $user, Clinic $clinic): bool
    {
        return match ($user->role) {
            UserRole::Patient => false,
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => (int) $user->clinic_id === (int) $clinic->id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return match ($user->role) {
            UserRole::Coordinator => true,
            default => false,
        };
    }

    public function update(User $user, Clinic $clinic): bool
    {
        return match ($user->role) {
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => (int) $user->clinic_id === (int) $clinic->id,
            default => false,
        };
    }

    public function delete(User $user, Clinic $clinic): bool
    {
        return match ($user->role) {
            UserRole::Coordinator => true,
            default => false,
        };
    }
}
