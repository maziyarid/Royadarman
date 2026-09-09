<?php

namespace App\Domain\Scheduling\Contracts;

use App\Models\AppointmentSlot;
use App\Models\ClinicBranch;
use Carbon\Carbon;

interface SlotService
{
    /**
     * Create appointment slots for a clinic branch
     */
    public function createSlots(ClinicBranch $branch, array $slotData): array;

    /**
     * Find available slots for a clinic branch
     */
    public function findAvailableSlots(ClinicBranch $branch, Carbon $startDate, Carbon $endDate): array;

    /**
     * Hold a slot for a patient
     */
    public function holdSlot(AppointmentSlot $slot, string $patientId, array $metadata): string;

    /**
     * Release a slot hold
     */
    public function releaseHold(string $holdToken): bool;

    /**
     * Convert a hold to a confirmed appointment
     */
    public function convertHold(string $holdToken, array $appointmentData): AppointmentSlot;

    /**
     * Check if a slot is available for booking
     */
    public function isSlotAvailable(AppointmentSlot $slot): bool;

    /**
     * Generate recurring slots
     */
    public function generateRecurringSlots(ClinicBranch $branch, array $pattern, Carbon $endDate): int;
}
