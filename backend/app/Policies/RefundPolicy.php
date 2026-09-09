<?php

namespace App\Policies;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Refund;
use App\Models\User;

final class RefundPolicy
{
    public function view(User $user, Refund $refund): bool
    {
        return match ($user->role) {
            UserRole::Patient => (int) $refund->order->patient_user_id === (int) $user->id,
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => (int) $refund->order->clinic_id === (int) $user->clinic_id,
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

    public function process(User $user, Refund $refund): bool
    {
        return match ($user->role) {
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => (int) $refund->order->clinic_id === (int) $user->clinic_id,
            default => false,
        };
    }
}
