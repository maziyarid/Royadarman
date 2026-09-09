<?php

namespace App\Policies;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return match ($user->role) {
            UserRole::Patient => (int) $order->patient_user_id === (int) $user->id,
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => (int) $order->clinic_id === (int) $user->clinic_id,
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

    public function submit(User $user, Order $order): bool
    {
        return match ($user->role) {
            UserRole::Patient => (int) $order->patient_user_id === (int) $user->id,
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => (int) $order->clinic_id === (int) $user->clinic_id,
            default => false,
        };
    }

    public function pay(User $user, Order $order): bool
    {
        return match ($user->role) {
            UserRole::Patient => (int) $order->patient_user_id === (int) $user->id,
            UserRole::Coordinator => true,
            default => false,
        };
    }

    public function cancel(User $user, Order $order): bool
    {
        return match ($user->role) {
            UserRole::Patient => (int) $order->patient_user_id === (int) $user->id,
            UserRole::Coordinator => true,
            UserRole::ClinicRepresentative => (int) $order->clinic_id === (int) $user->clinic_id,
            default => false,
        };
    }
}
