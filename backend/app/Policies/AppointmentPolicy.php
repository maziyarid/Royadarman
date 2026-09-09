<?php

namespace App\Policies;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return match ($user->role) {
            UserRole::Patient => (int) $appointment->patient_user_id === (int) $user->id,
            UserRole::Coordinator => $user->can('coordinate', Appointment::class),
            UserRole::Clinician => $this->isAssignedClinician($user, $appointment),
            UserRole::ClinicRepresentative => $this->isClinicRepresentative($user, $appointment),
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

    public function confirm(User $user, Appointment $appointment): bool
    {
        return match ($user->role) {
            UserRole::Patient => (int) $appointment->patient_user_id === (int) $user->id,
            UserRole::Coordinator => $this->view($user, $appointment),
            UserRole::Clinician => $this->isAssignedClinician($user, $appointment),
            UserRole::ClinicRepresentative => $this->isClinicRepresentative($user, $appointment),
            default => false,
        };
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return match ($user->role) {
            UserRole::Patient => (int) $appointment->patient_user_id === (int) $user->id,
            UserRole::Coordinator => $this->view($user, $appointment),
            UserRole::Clinician => $this->isAssignedClinician($user, $appointment),
            UserRole::ClinicRepresentative => $this->isClinicRepresentative($user, $appointment),
            default => false,
        };
    }

    public function checkIn(User $user, Appointment $appointment): bool
    {
        return match ($user->role) {
            UserRole::ClinicRepresentative => $this->isClinicRepresentative($user, $appointment),
            UserRole::Coordinator => $this->view($user, $appointment),
            default => false,
        };
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        return match ($user->role) {
            UserRole::Clinician => $this->isAssignedClinician($user, $appointment),
            UserRole::ClinicRepresentative => $this->isClinicRepresentative($user, $appointment),
            default => false,
        };
    }

    private function isAssignedClinician(User $user, Appointment $appointment): bool
    {
        if ($appointment->dentist_id) {
            return DB::table('dentists')->where('id', $appointment->dentist_id)->where('user_id', $user->id)->exists();
        }

        return false;
    }

    private function isClinicRepresentative(User $user, Appointment $appointment): bool
    {
        return DB::table('clinic_memberships')
            ->where('user_id', $user->id)
            ->where('clinic_id', $appointment->clinic_id)
            ->exists();
    }
}
