<?php

namespace App\Http\Controllers\Api\V1\Scheduling;

use App\Domain\Finance\Services\LedgerService;
use App\Domain\Finance\Services\PaymentService;
use App\Domain\Operations\Services\Idempotency;
use App\Domain\Operations\Services\Outbox;
use App\Domain\Scheduling\Services\SlotService;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\ClinicBranch;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\ReferralRequest;
use App\Models\SlotHold;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AppointmentController extends Controller
{
    public function __construct(
        private readonly SlotService $slotService,
        private readonly PaymentService $paymentService,
        private readonly LedgerService $ledgerService,
        private readonly Idempotency $idempotency,
        private readonly Outbox $outbox
    ) {}

    public function createFromSlot(Request $request, AppointmentSlot $slot): JsonResponse
    {
        $data = $request->validate([
            'referral_request_id' => ['required', 'string'],
            'visit_fee' => ['required', 'integer', 'min:0'],
            'currency' => ['string', 'size:3'],
        ]);

        return DB::transaction(function () use ($request, $slot, $data): JsonResponse {
            // Check if slot is available
            if (! $this->slotService->isSlotAvailable($slot)) {
                abort(409, 'Slot is not available');
            }

            // Get referral request
            $referralRequest = ReferralRequest::query()->findOrFail($data['referral_request_id']);

            // Create the appointment
            $appointment = Appointment::query()->create([
                'id' => (string) Str::ulid(),
                'public_reference' => 'APT-' . strtoupper(Str::random(8)),
                'clinic_branch_id' => $slot->clinic_branch_id,
                'clinic_id' => $slot->clinicBranch->clinic_id,
                'dentist_id' => $slot->dentist_id,
                'service_id' => $slot->service_id,
                'patient_user_id' => $request->user()->id,
                'appointment_slot_id' => $slot->id,
                'date' => $slot->date,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'duration_minutes' => $slot->duration_minutes,
                'visit_fee' => $data['visit_fee'],
                'visit_fee_currency' => $data['currency'] ?? 'IRR',
                'status' => 'pending',
                'booking_mode' => 'instant',
                'notes' => null,
            ]);

            // Update slot status
            $slot->update(['status' => 'booked']);

            // Create order
            $order = Order::query()->create([
                'id' => (string) Str::ulid(),
                'public_reference' => 'ORD-' . strtoupper(Str::random(8)),
                'appointment_id' => $appointment->id,
                'referral_request_id' => $referralRequest->id,
                'patient_user_id' => $request->user()->id,
                'type' => 'visit_fee',
                'status' => 'draft',
                'subtotal_amount' => $data['visit_fee'],
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $data['visit_fee'],
                'currency' => $data['currency'] ?? 'IRR',
                'payment_method' => null,
                'payment_gateway' => null,
                'gateway_reference' => null,
                'notes' => null,
                'submitted_at' => null,
                'paid_at' => null,
                'expires_at' => now()->addHours(2),
            ]);

            // Create order line
            $order->orderLines()->create([
                'id' => (string) Str::ulid(),
                'service_id' => $slot->service_id,
                'type' => 'visit_fee',
                'name' => 'Visit Fee',
                'description' => 'Appointment visit fee',
                'quantity' => 1,
                'unit_price' => $data['visit_fee'],
                'total_price' => $data['visit_fee'],
                'currency' => $data['currency'] ?? 'IRR',
                'metadata' => null,
                'sort_order' => 0,
            ]);

            // Update referral request
            $referralRequest->update([
                'matched_at' => now(),
                'status' => 'matched',
            ]);

            // Send notification
            $this->outbox->record(
                'appointment.created',
                Appointment::class,
                $appointment->id,
                [
                    'template_key' => 'appointment_created',
                    'reference' => $appointment->public_reference,
                    'clinic_name' => $slot->clinicBranch->clinic->name,
                    'date' => $appointment->date->toDateString(),
                    'time' => $appointment->start_time->format('H:i'),
                ],
                'appointment.created.' . $appointment->id,
                $request->user()->locale
            );

            return response()->json([
                'data' => [
                    'appointment' => $this->appointmentResource($appointment),
                    'order' => [
                        'id' => $order->id,
                        'public_reference' => $order->public_reference,
                        'total_amount' => $order->total_amount,
                        'currency' => $order->currency,
                        'status' => $order->status,
                    ],
                ],
            ], 201);
        });
    }

    public function createFromCapacityWindow(Request $request, ClinicBranch $clinicBranch): JsonResponse
    {
        $data = $request->validate([
            'capacity_window_id' => ['required', 'string'],
            'referral_request_id' => ['required', 'string'],
            'visit_fee' => ['required', 'integer', 'min:0'],
            'currency' => ['string', 'size:3'],
        ]);

        return DB::transaction(function () use ($request, $clinicBranch, $data): JsonResponse {
            // Get capacity window
            $capacityWindow = $clinicBranch->capacityWindows()
                ->where('id', $data['capacity_window_id'])
                ->where('status', 'open')
                ->firstOrFail();

            // Check if window has capacity
            if ($capacityWindow->booked_count >= $capacityWindow->capacity) {
                abort(409, 'Capacity window is full');
            }

            // Get referral request
            $referralRequest = ReferralRequest::query()->findOrFail($data['referral_request_id']);

            // Create the appointment
            $appointment = Appointment::query()->create([
                'id' => (string) Str::ulid(),
                'public_reference' => 'APT-' . strtoupper(Str::random(8)),
                'clinic_branch_id' => $clinicBranch->id,
                'clinic_id' => $clinicBranch->clinic_id,
                'dentist_id' => $capacityWindow->dentist_id,
                'service_id' => $capacityWindow->service_id,
                'patient_user_id' => $request->user()->id,
                'capacity_window_id' => $capacityWindow->id,
                'date' => $capacityWindow->date,
                'start_time' => $capacityWindow->start_time,
                'end_time' => $capacityWindow->end_time,
                'duration_minutes' => (int) $capacityWindow->start_time->diffInMinutes($capacityWindow->end_time),
                'visit_fee' => $data['visit_fee'],
                'visit_fee_currency' => $data['currency'] ?? 'IRR',
                'status' => 'pending',
                'booking_mode' => 'manual',
                'notes' => null,
            ]);

            // Update capacity window
            $capacityWindow->increment('booked_count');

            if ($capacityWindow->booked_count >= $capacityWindow->capacity) {
                $capacityWindow->update(['status' => 'full']);
            }

            // Create order
            $order = Order::query()->create([
                'id' => (string) Str::ulid(),
                'public_reference' => 'ORD-' . strtoupper(Str::random(8)),
                'appointment_id' => $appointment->id,
                'referral_request_id' => $referralRequest->id,
                'patient_user_id' => $request->user()->id,
                'type' => 'visit_fee',
                'status' => 'draft',
                'subtotal_amount' => $data['visit_fee'],
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $data['visit_fee'],
                'currency' => $data['currency'] ?? 'IRR',
                'payment_method' => null,
                'payment_gateway' => null,
                'gateway_reference' => null,
                'notes' => null,
                'submitted_at' => null,
                'paid_at' => null,
                'expires_at' => now()->addHours(2),
            ]);

            // Update referral request
            $referralRequest->update([
                'matched_at' => now(),
                'status' => 'matched',
            ]);

            // Send notification
            $this->outbox->record(
                'appointment.created',
                Appointment::class,
                $appointment->id,
                [
                    'template_key' => 'appointment_created',
                    'reference' => $appointment->public_reference,
                    'clinic_name' => $clinicBranch->clinic->name,
                    'date' => $appointment->date->toDateString(),
                    'time' => $appointment->start_time->format('H:i'),
                ],
                'appointment.created.' . $appointment->id,
                $request->user()->locale
            );

            return response()->json([
                'data' => [
                    'appointment' => $this->appointmentResource($appointment),
                    'order' => [
                        'id' => $order->id,
                        'public_reference' => $order->public_reference,
                        'total_amount' => $order->total_amount,
                        'currency' => $order->currency,
                        'status' => $order->status,
                    ],
                ],
            ], 201);
        });
    }

    public function confirm(Request $request, Appointment $appointment): JsonResponse
    {
        abort_unless(
            $appointment->patient_user_id === $request->user()->id ||
            $request->user()->can('confirm', $appointment),
            404
        );

        return DB::transaction(function () use ($request, $appointment): JsonResponse {
            $appointment->update(['status' => 'confirmed']);

            // Create status history
            $appointment->statusHistory()->create([
                'id' => (string) Str::ulid(),
                'from_status' => 'pending',
                'to_status' => 'confirmed',
                'reason' => 'patient_confirmed',
                'notes' => null,
                'changed_at' => now(),
                'changed_by_user_id' => $request->user()->id,
            ]);

            // Send notification
            $this->outbox->record(
                'appointment.confirmed',
                Appointment::class,
                $appointment->id,
                [
                    'template_key' => 'appointment_confirmed',
                    'reference' => $appointment->public_reference,
                ],
                'appointment.confirmed.' . $appointment->id,
                $request->user()->locale
            );

            return response()->json(['data' => $this->appointmentResource($appointment)]);
        });
    }

    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        abort_unless(
            $appointment->patient_user_id === $request->user()->id ||
            $request->user()->can('cancel', $appointment),
            404
        );

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
        ]);

        return DB::transaction(function () use ($request, $appointment, $data): JsonResponse {
            $previousStatus = $appointment->status;
            $appointment->update(['status' => 'cancelled']);

            // Create status history
            $appointment->statusHistory()->create([
                'id' => (string) Str::ulid(),
                'from_status' => $previousStatus,
                'to_status' => 'cancelled',
                'reason' => $data['reason'],
                'notes' => null,
                'changed_at' => now(),
                'changed_by_user_id' => $request->user()->id,
            ]);

            // Release slot if it was booked
            if ($appointment->appointmentSlot) {
                $this->slotService->releaseHold($appointment->slotHold?->token ?? '');
                $appointment->appointmentSlot->update(['status' => 'available']);
            }

            // Release capacity window
            if ($appointment->capacityWindow) {
                $appointment->capacityWindow->decrement('booked_count');
                if ($appointment->capacityWindow->booked_count < $appointment->capacityWindow->capacity) {
                    $appointment->capacityWindow->update(['status' => 'open']);
                }
            }

            // Send notification
            $this->outbox->record(
                'appointment.cancelled',
                Appointment::class,
                $appointment->id,
                [
                    'template_key' => 'appointment_cancelled',
                    'reference' => $appointment->public_reference,
                    'reason' => $data['reason'],
                ],
                'appointment.cancelled.' . $appointment->id,
                $request->user()->locale
            );

            return response()->json(['data' => $this->appointmentResource($appointment)]);
        });
    }

    public function checkIn(Request $request, Appointment $appointment): JsonResponse
    {
        abort_unless($request->user()->can('checkIn', $appointment), 404);

        return DB::transaction(function () use ($request, $appointment): JsonResponse {
            // Create check-in
            $checkIn = $appointment->checkIns()->create([
                'id' => (string) Str::ulid(),
                'checked_in_by_user_id' => $request->user()->id,
                'checked_in_at' => now(),
                'expected_arrival_at' => null,
                'status' => 'checked_in',
                'method' => 'in_person',
                'notes' => null,
            ]);

            // Update appointment status
            $appointment->update(['status' => 'confirmed']);

            // Create status history
            $appointment->statusHistory()->create([
                'id' => (string) Str::ulid(),
                'from_status' => $appointment->status,
                'to_status' => 'confirmed',
                'reason' => 'checked_in',
                'notes' => null,
                'changed_at' => now(),
                'changed_by_user_id' => $request->user()->id,
            ]);

            return response()->json([
                'data' => [
                    'appointment' => $this->appointmentResource($appointment),
                    'check_in' => [
                        'id' => $checkIn->id,
                        'checked_in_at' => $checkIn->checked_in_at->toIso8601String(),
                        'status' => $checkIn->status,
                        'method' => $checkIn->method,
                    ],
                ],
            ]);
        });
    }

    public function complete(Request $request, Appointment $appointment): JsonResponse
    {
        abort_unless($request->user()->can('complete', $appointment), 404);

        $data = $request->validate([
            'outcome' => ['required', 'in:completed,partial,cancelled,referred'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'requires_follow_up' => ['boolean'],
            'follow_up_due_at' => ['nullable', 'date'],
        ]);

        return DB::transaction(function () use ($request, $appointment, $data): JsonResponse {
            // Create visit confirmation
            $confirmation = $appointment->visitConfirmations()->create([
                'id' => (string) Str::ulid(),
                'confirmed_by_user_id' => $request->user()->id,
                'confirmed_at' => now(),
                'outcome' => $data['outcome'],
                'summary' => $data['summary'],
                'notes' => $data['notes'],
                'requires_follow_up' => $data['requires_follow_up'] ?? false,
                'follow_up_due_at' => $data['follow_up_due_at'],
            ]);

            // Update appointment status
            $appointment->update(['status' => 'completed']);

            // Create status history
            $appointment->statusHistory()->create([
                'id' => (string) Str::ulid(),
                'from_status' => 'confirmed',
                'to_status' => 'completed',
                'reason' => 'visit_completed',
                'notes' => $data['summary'],
                'changed_at' => now(),
                'changed_by_user_id' => $request->user()->id,
            ]);

            // Send notification
            $this->outbox->record(
                'appointment.completed',
                Appointment::class,
                $appointment->id,
                [
                    'template_key' => 'appointment_completed',
                    'reference' => $appointment->public_reference,
                    'outcome' => $data['outcome'],
                ],
                'appointment.completed.' . $appointment->id,
                $appointment->patient->locale ?? 'fa'
            );

            return response()->json([
                'data' => [
                    'appointment' => $this->appointmentResource($appointment),
                    'visit_confirmation' => [
                        'id' => $confirmation->id,
                        'confirmed_at' => $confirmation->confirmed_at->toIso8601String(),
                        'outcome' => $confirmation->outcome,
                        'summary' => $confirmation->summary,
                        'requires_follow_up' => $confirmation->requires_follow_up,
                        'follow_up_due_at' => $confirmation->follow_up_due_at?->toIso8601String(),
                    ],
                ],
            ]);
        });
    }

    public function getAvailableSlots(Request $request, ClinicBranch $clinicBranch): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);

        $slots = $this->slotService->findAvailableSlots($clinicBranch, $startDate, $endDate);

        return response()->json(['data' => ['slots' => $slots]]);
    }

    public function holdSlot(Request $request, AppointmentSlot $slot): JsonResponse
    {
        $token = $this->slotService->holdSlot(
            $slot,
            $request->user()->id,
            [
                'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
                'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
                'metadata' => json_encode(['source' => 'api']),
            ]
        );

        return response()->json([
            'data' => [
                'hold_token' => $token,
                'expires_in' => 600, // 10 minutes
                'slot' => [
                    'id' => $slot->id,
                    'date' => $slot->date->toDateString(),
                    'start_time' => $slot->start_time->format('H:i'),
                    'end_time' => $slot->end_time->format('H:i'),
                    'status' => $slot->refresh()->status,
                ],
            ],
        ]);
    }

    public function releaseHold(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hold_token' => ['required', 'string'],
        ]);

        $released = $this->slotService->releaseHold($data['hold_token']);

        return response()->json(['data' => ['released' => $released]]);
    }

    public function convertHold(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hold_token' => ['required', 'string'],
            'referral_request_id' => ['required', 'string'],
            'visit_fee' => ['required', 'integer', 'min:0'],
            'currency' => ['string', 'size:3'],
        ]);

        return DB::transaction(function () use ($request, $data): JsonResponse {
            $slot = $this->slotService->convertHold($data['hold_token'], []);

            // Get referral request
            $referralRequest = ReferralRequest::query()->findOrFail($data['referral_request_id']);

            // Create the appointment
            $appointment = Appointment::query()->create([
                'id' => (string) Str::ulid(),
                'public_reference' => 'APT-' . strtoupper(Str::random(8)),
                'clinic_branch_id' => $slot->clinic_branch_id,
                'clinic_id' => $slot->clinicBranch->clinic_id,
                'dentist_id' => $slot->dentist_id,
                'service_id' => $slot->service_id,
                'patient_user_id' => $request->user()->id,
                'slot_hold_id' => $slot->slotHolds()->latest()->first()->id,
                'appointment_slot_id' => $slot->id,
                'date' => $slot->date,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'duration_minutes' => $slot->duration_minutes,
                'visit_fee' => $data['visit_fee'],
                'visit_fee_currency' => $data['currency'] ?? 'IRR',
                'status' => 'pending',
                'booking_mode' => 'instant',
                'notes' => null,
            ]);

            // Update referral request
            $referralRequest->update([
                'matched_at' => now(),
                'status' => 'matched',
            ]);

            return response()->json([
                'data' => [
                    'appointment' => $this->appointmentResource($appointment),
                ],
            ], 201);
        });
    }

    private function appointmentResource(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'public_reference' => $appointment->public_reference,
            'clinic_branch_id' => $appointment->clinic_branch_id,
            'clinic_id' => $appointment->clinic_id,
            'clinic_name' => $appointment->clinicBranch?->clinic?->name,
            'clinic_branch_name' => $appointment->clinicBranch?->name,
            'dentist_id' => $appointment->dentist_id,
            'dentist_name' => $appointment->dentist?->first_name . ' ' . $appointment->dentist?->last_name,
            'service_id' => $appointment->service_id,
            'service_name' => $appointment->service?->name_fa,
            'patient_user_id' => $appointment->patient_user_id,
            'patient_name' => $appointment->patient?->name,
            'date' => $appointment->date->toDateString(),
            'start_time' => $appointment->start_time->format('H:i'),
            'end_time' => $appointment->end_time->format('H:i'),
            'duration_minutes' => $appointment->duration_minutes,
            'visit_fee' => $appointment->visit_fee,
            'visit_fee_currency' => $appointment->visit_fee_currency,
            'status' => $appointment->status,
            'booking_mode' => $appointment->booking_mode,
            'notes' => $appointment->notes,
            'created_at' => $appointment->created_at->toIso8601String(),
            'updated_at' => $appointment->updated_at->toIso8601String(),
        ];
    }
}
