<?php

namespace App\Policies;

use App\Domain\Identity\Enums\UserRole;
use App\Models\PaymentIntent;
use App\Models\User;

final class PaymentIntentPolicy
{
    public function view(User $user, PaymentIntent $paymentIntent): bool
    {
        return match ($user->role) {
            UserRole::Patient => (int) $paymentIntent->order->patient_user_id === (int) $user->id,
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => (int) $paymentIntent->order->clinic_id === (int) $user->clinic_id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return match ($user->role) {
            UserRole::Patient => true,
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => true,
            default => false,
        };
    }
}
