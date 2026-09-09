<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domain\Finance\Enums\OrderStatus;
use App\Domain\Finance\Services\LedgerService;
use App\Domain\Finance\Services\PaymentService;
use App\Domain\Operations\Services\Idempotency;
use App\Domain\Operations\Services\Outbox;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\PaymentIntent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class OrderController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly LedgerService $ledgerService,
        private readonly Idempotency $idempotency,
        private readonly Outbox $outbox
    ) {}

    public function create(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:visit_fee,service,package'],
            'subtotal_amount' => ['required', 'integer', 'min:0'],
            'discount_amount' => ['integer', 'min:0'],
            'tax_amount' => ['integer', 'min:0'],
            'total_amount' => ['required', 'integer', 'min:0'],
            'currency' => ['string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($request, $appointment, $data): JsonResponse {
            // Check if appointment belongs to user
            abort_unless(
                $appointment->patient_user_id === $request->user()->id ||
                $request->user()->can('create', Order::class),
                404
            );

            // Verify totals
            $calculatedTotal = $data['subtotal_amount'] - ($data['discount_amount'] ?? 0) + ($data['tax_amount'] ?? 0);
            if ($calculatedTotal !== $data['total_amount']) {
                abort(422, 'Total amount does not match calculated amount');
            }

            $order = Order::query()->create([
                'id' => (string) Str::ulid(),
                'public_reference' => 'ORD-' . strtoupper(Str::random(8)),
                'appointment_id' => $appointment->id,
                'patient_user_id' => $appointment->patient_user_id,
                'type' => $data['type'],
                'status' => OrderStatus::Draft->value,
                'subtotal_amount' => $data['subtotal_amount'],
                'discount_amount' => $data['discount_amount'] ?? 0,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'total_amount' => $data['total_amount'],
                'currency' => $data['currency'] ?? 'IRR',
                'payment_method' => null,
                'payment_gateway' => null,
                'gateway_reference' => null,
                'notes' => $data['notes'],
                'submitted_at' => null,
                'paid_at' => null,
                'expires_at' => now()->addHours(2),
            ]);

            return response()->json(['data' => $this->resource($order)], 201);
        });
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless(
            $order->patient_user_id === $request->user()->id ||
            $request->user()->can('view', $order),
            404
        );

        $order->load([
            'appointment.clinicBranch.clinic',
            'appointment.dentist',
            'appointment.service',
            'patient',
            'orderLines.service',
            'paymentIntents',
            'paymentTransactions',
        ]);

        return response()->json(['data' => $this->resource($order)]);
    }

    public function submit(Request $request, Order $order, Idempotency $idempotency): JsonResponse
    {
        abort_unless(
            $order->patient_user_id === $request->user()->id ||
            $request->user()->can('submit', $order),
            404
        );

        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'payment_gateway' => ['required', 'in:zarinpal,idpay'],
        ]);

        $result = $idempotency->execute(
            $request->user(),
            'order.submit.' . $order->id,
            (string) $request->header('Idempotency-Key'),
            $data,
            function () use ($request, $order, $data): array {
                return DB::transaction(function () use ($request, $order, $data): array {
                    $locked = Order::query()
                        ->lockForUpdate()
                        ->findOrFail($order->id);

                    if ($locked->status !== OrderStatus::Draft->value || $locked->version !== (int) $data['version']) {
                        abort(409);
                    }

                    // Update order
                    $locked->update([
                        'status' => OrderStatus::Pending->value,
                        'version' => $locked->version + 1,
                        'payment_gateway' => $data['payment_gateway'],
                        'submitted_at' => now(),
                    ]);

                    // Create payment intent
                    $paymentIntent = $this->paymentService->createPaymentIntent(
                        $locked->refresh(),
                        $data['payment_gateway'],
                        ['callback_url' => route('api.v1.payments.callback')]
                    );

                    // Record in ledger
                    $this->ledgerService->recordOrder($locked);

                    // Send notification
                    $this->outbox->record(
                        'order.submitted',
                        Order::class,
                        $locked->id,
                        [
                            'template_key' => 'order_submitted',
                            'reference' => $locked->public_reference,
                            'amount' => $locked->total_amount,
                            'currency' => $locked->currency,
                        ],
                        'order.submitted.' . $locked->id,
                        $request->user()->locale
                    );

                    return [
                        'status' => 200,
                        'body' => [
                            'data' => [
                                'order' => $this->resource($locked),
                                'payment_intent' => [
                                    'id' => $paymentIntent->id,
                                    'gateway' => $paymentIntent->gateway,
                                    'amount' => $paymentIntent->amount,
                                    'currency' => $paymentIntent->currency,
                                    'status' => $paymentIntent->status,
                                    'payment_url' => $paymentIntent->payment_url,
                                    'expires_at' => $paymentIntent->expires_at->toIso8601String(),
                                ],
                            ],
                        ],
                    ];
                });
            }
        );

        return response()->json($result['body'], $result['status']);
    }

    public function pay(Request $request, Order $order): JsonResponse
    {
        abort_unless(
            $order->patient_user_id === $request->user()->id ||
            $request->user()->can('pay', $order),
            404
        );

        $data = $request->validate([
            'payment_gateway' => ['required', 'in:zarinpal,idpay'],
        ]);

        return DB::transaction(function () use ($request, $order, $data): JsonResponse {
            // Create payment intent
            $paymentIntent = $this->paymentService->createPaymentIntent(
                $order,
                $data['payment_gateway'],
                ['callback_url' => route('api.v1.payments.callback')]
            );

            // Update order
            $order->update([
                'payment_gateway' => $data['payment_gateway'],
                'status' => OrderStatus::Pending->value,
                'submitted_at' => now(),
            ]);

            return response()->json([
                'data' => [
                    'order' => $this->resource($order),
                    'payment_intent' => [
                        'id' => $paymentIntent->id,
                        'gateway' => $paymentIntent->gateway,
                        'amount' => $paymentIntent->amount,
                        'currency' => $paymentIntent->currency,
                        'status' => $paymentIntent->status,
                        'payment_url' => $paymentIntent->payment_url,
                        'expires_at' => $paymentIntent->expires_at->toIso8601String(),
                    ],
                ],
            ]);
        });
    }

    public function verify(Request $request, Order $order): JsonResponse
    {
        abort_unless(
            $order->patient_user_id === $request->user()->id ||
            $request->user()->can('view', $order),
            404
        );

        $paymentIntent = $order->paymentIntents()->latest()->first();

        if (! $paymentIntent) {
            return response()->json(['data' => ['status' => 'no_payment_intent']]);
        }

        // Check intent status
        $intent = $this->paymentService->checkIntentStatus($paymentIntent);

        return response()->json(['data' => [
            'order' => $this->resource($order),
            'payment_intent' => [
                'id' => $intent->id,
                'status' => $intent->status,
                'gateway_reference' => $intent->gateway_reference,
                'gateway_response' => $intent->gateway_response,
            ],
        ]]);
    }

    public function cancel(Request $request, Order $order, Idempotency $idempotency): JsonResponse
    {
        abort_unless(
            $order->patient_user_id === $request->user()->id ||
            $request->user()->can('cancel', $order),
            404
        );

        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $result = $idempotency->execute(
            $request->user(),
            'order.cancel.' . $order->id,
            (string) $request->header('Idempotency-Key'),
            $data,
            function () use ($request, $order, $data): array {
                return DB::transaction(function () use ($request, $order, $data): array {
                    $locked = Order::query()
                        ->lockForUpdate()
                        ->findOrFail($order->id);

                    if ($locked->status->isPaid() || $locked->status->isCancelled()) {
                        abort(409);
                    }

                    if ($locked->version !== (int) $data['version']) {
                        abort(409);
                    }

                    $locked->update([
                        'status' => OrderStatus::Cancelled->value,
                        'version' => $locked->version + 1,
                    ]);

                    // Cancel payment intent
                    $paymentIntent = $locked->paymentIntents()->latest()->first();
                    if ($paymentIntent && ! $paymentIntent->status->isCompleted()) {
                        $paymentIntent->update(['status' => 'cancelled']);
                    }

                    // Send notification
                    $this->outbox->record(
                        'order.cancelled',
                        Order::class,
                        $locked->id,
                        [
                            'template_key' => 'order_cancelled',
                            'reference' => $locked->public_reference,
                            'reason' => $data['reason'],
                        ],
                        'order.cancelled.' . $locked->id,
                        $request->user()->locale
                    );

                    return [
                        'status' => 200,
                        'body' => ['data' => $this->resource($locked->refresh())],
                    ];
                });
            }
        );

        return response()->json($result['body'], $result['status']);
    }

    private function resource(Order $order): array
    {
        return [
            'id' => $order->id,
            'public_reference' => $order->public_reference,
            'appointment_id' => $order->appointment_id,
            'appointment_reference' => $order->appointment?->public_reference,
            'clinic_id' => $order->appointment?->clinic_id,
            'clinic_name' => $order->appointment?->clinic?->name,
            'patient_user_id' => $order->patient_user_id,
            'patient_name' => $order->patient?->name,
            'type' => $order->type,
            'status' => $order->status->value,
            'subtotal_amount' => $order->subtotal_amount,
            'discount_amount' => $order->discount_amount,
            'tax_amount' => $order->tax_amount,
            'total_amount' => $order->total_amount,
            'currency' => $order->currency,
            'payment_method' => $order->payment_method,
            'payment_gateway' => $order->payment_gateway,
            'gateway_reference' => $order->gateway_reference,
            'notes' => $order->notes,
            'submitted_at' => $order->submitted_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
            'expires_at' => $order->expires_at?->toIso8601String(),
            'created_at' => $order->created_at->toIso8601String(),
            'updated_at' => $order->updated_at->toIso8601String(),
        ];
    }
}
