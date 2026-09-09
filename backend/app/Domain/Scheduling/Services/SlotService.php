<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Scheduling\Contracts\SlotService as SlotServiceContract;
use App\Domain\Scheduling\Enums\HoldStatus;
use App\Domain\Scheduling\Enums\SlotStatus;
use App\Models\AppointmentSlot;
use App\Models\ClinicBranch;
use App\Models\SlotHold;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SlotService implements SlotServiceContract
{
    private const DEFAULT_HOLD_EXPIRY_MINUTES = 10;

    public function createSlots(ClinicBranch $branch, array $slotData): array
    {
        return DB::transaction(function () use ($branch, $slotData): array {
            $created = [];

            foreach ($slotData as $data) {
                $slot = AppointmentSlot::query()->create([
                    'id' => (string) Str::ulid(),
                    'clinic_branch_id' => $branch->id,
                    'dentist_id' => $data['dentist_id'] ?? null,
                    'service_id' => $data['service_id'] ?? null,
                    'date' => $data['date'],
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'duration_minutes' => $data['duration_minutes'],
                    'price' => $data['price'] ?? null,
                    'price_currency' => $data['price_currency'] ?? 'IRR',
                    'status' => SlotStatus::Available->value,
                    'is_recurring' => $data['is_recurring'] ?? false,
                    'recurrence_pattern' => $data['recurrence_pattern'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                $created[] = $slot;
            }

            return $created;
        });
    }

    public function findAvailableSlots(ClinicBranch $branch, Carbon $startDate, Carbon $endDate): array
    {
        return AppointmentSlot::query()
            ->where('clinic_branch_id', $branch->id)
            ->where('status', SlotStatus::Available->value)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->orderBy('start_time')
            ->with(['service', 'dentist'])
            ->get()
            ->toArray();
    }

    public function holdSlot(AppointmentSlot $slot, string $patientId, array $metadata): string
    {
        return DB::transaction(function () use ($slot, $patientId, $metadata): string {
            $holdToken = (string) Str::ulid();

            // Lock the slot for update
            $lockedSlot = AppointmentSlot::query()
                ->where('id', $slot->id)
                ->where('status', SlotStatus::Available->value)
                ->lockForUpdate()
                ->firstOrFail();

            // Create the hold
            $hold = SlotHold::query()->create([
                'id' => (string) Str::ulid(),
                'appointment_slot_id' => $slot->id,
                'patient_user_id' => $patientId,
                'token' => $holdToken,
                'status' => HoldStatus::Active->value,
                'expires_at' => now()->addMinutes(self::DEFAULT_HOLD_EXPIRY_MINUTES),
                'created_at' => now(),
                'ip_hash' => $metadata['ip_hash'] ?? null,
                'user_agent_hash' => $metadata['user_agent_hash'] ?? null,
                'metadata' => $metadata['metadata'] ?? null,
            ]);

            // Update slot status
            $lockedSlot->update(['status' => SlotStatus::Held->value]);

            return $holdToken;
        });
    }

    public function releaseHold(string $holdToken): bool
    {
        return DB::transaction(function () use ($holdToken): bool {
            $hold = SlotHold::query()
                ->where('token', $holdToken)
                ->where('status', HoldStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if (! $hold || $hold->expires_at->isPast()) {
                return false;
            }

            // Release the hold
            $hold->update([
                'status' => HoldStatus::Cancelled->value,
                'cancelled_at' => now(),
            ]);

            // Restore slot to available
            $hold->appointmentSlot->update(['status' => SlotStatus::Available->value]);

            return true;
        });
    }

    public function convertHold(string $holdToken, array $appointmentData): AppointmentSlot
    {
        return DB::transaction(function () use ($holdToken, $appointmentData): AppointmentSlot {
            $hold = SlotHold::query()
                ->where('token', $holdToken)
                ->where('status', HoldStatus::Active->value)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->firstOrFail();

            // Mark hold as converted
            $hold->update([
                'status' => HoldStatus::Converted->value,
                'converted_at' => now(),
            ]);

            // Update slot status to booked
            $slot = $hold->appointmentSlot;
            $slot->update(['status' => SlotStatus::Booked->value]);

            return $slot;
        });
    }

    public function isSlotAvailable(AppointmentSlot $slot): bool
    {
        if ($slot->status !== SlotStatus::Available->value) {
            return false;
        }

        // Check if slot is in the past
        $slotDateTime = $slot->date->format('Y-m-d') . ' ' . $slot->start_time->format('H:i:s');
        if (strtotime($slotDateTime) < time()) {
            return false;
        }

        // Check if slot has an active hold
        $hasActiveHold = SlotHold::query()
            ->where('appointment_slot_id', $slot->id)
            ->where('status', HoldStatus::Active->value)
            ->where('expires_at', '>', now())
            ->exists();

        return ! $hasActiveHold;
    }

    public function generateRecurringSlots(ClinicBranch $branch, array $pattern, Carbon $endDate): int
    {
        return DB::transaction(function () use ($branch, $pattern, $endDate): int {
            $count = 0;
            $currentDate = Carbon::parse($pattern['start_date']);

            while ($currentDate->lte($endDate)) {
                // Check if this date matches the recurrence pattern
                if ($this->matchesRecurrencePattern($currentDate, $pattern)) {
                    $slots = $this->createSlots($branch, [
                        [
                            'dentist_id' => $pattern['dentist_id'] ?? null,
                            'service_id' => $pattern['service_id'] ?? null,
                            'date' => $currentDate->toDateString(),
                            'start_time' => $pattern['start_time'],
                            'end_time' => $pattern['end_time'],
                            'duration_minutes' => $pattern['duration_minutes'],
                            'price' => $pattern['price'] ?? null,
                            'price_currency' => $pattern['price_currency'] ?? 'IRR',
                            'is_recurring' => true,
                            'recurrence_pattern' => json_encode($pattern['recurrence']),
                            'notes' => $pattern['notes'] ?? null,
                        ],
                    ]);

                    $count += count($slots);
                }

                $currentDate = $currentDate->copy()->addDay();
            }

            return $count;
        });
    }

    /**
     * Check if a date matches the recurrence pattern
     */
    private function matchesRecurrencePattern(Carbon $date, array $pattern): bool
    {
        if (! isset($pattern['recurrence'])) {
            return true;
        }

        $recurrence = $pattern['recurrence'];

        // Check day of week
        if (isset($recurrence['days_of_week'])) {
            $dayOfWeek = strtolower($date->format('l'));
            if (! in_array($dayOfWeek, $recurrence['days_of_week'])) {
                return false;
            }
        }

        // Check day of month
        if (isset($recurrence['days_of_month'])) {
            if (! in_array($date->day, $recurrence['days_of_month'])) {
                return false;
            }
        }

        // Check week of month
        if (isset($recurrence['weeks_of_month'])) {
            $weekOfMonth = (int) ceil($date->day / 7);
            if (! in_array($weekOfMonth, $recurrence['weeks_of_month'])) {
                return false;
            }
        }

        // Check month
        if (isset($recurrence['months'])) {
            if (! in_array($date->month, $recurrence['months'])) {
                return false;
            }
        }

        return true;
    }
}
