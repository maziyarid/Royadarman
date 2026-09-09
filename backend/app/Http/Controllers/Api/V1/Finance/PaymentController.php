<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domain\Finance\Services\LedgerService;
use App\Domain\Finance\Services\PaymentService;
use App\Domain\Operations\Services\Outbox;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly LedgerService $ledgerService,
        private readonly Outbox $outbox
    ) {}

    public function createIntent(Request $request, Order $order): JsonResponse
    {
        abort_unless(
            $order->patient_user_id === $request->user()->id ||
            $request->user()->can('create', PaymentIntent::class),
            404
        );

        $data = $request->validate([
            'gateway' => ['required', 'in:zarinpal,idpay'],
            'callback_url' => ['nullable', 'url'],
        ]);

        return DB::transaction(function () use ($request, $order, $data): JsonResponse {
            $paymentIntent = $this->paymentService->createPaymentIntent(
                $order,
                $data['gateway'],
                ['callback_url' => $data['callback_url'] ?? route('api.v1.payments.callback')]
            );

            return response()->json([
                'data' => [
                    'payment_intent' => [
                        'id' => $paymentIntent->id,
                        'order_id' => $paymentIntent->order_id,
                        'gateway' => $paymentIntent->gateway,
                        'amount' => $paymentIntent->amount,
                        'currency' => $paymentIntent->currency,
                        'status' => $paymentIntent->status,
                        'payment_url' => $paymentIntent->payment_url,
                        'expires_at' => $paymentIntent->expires_at->toIso8601String(),
                        'created_at' => $paymentIntent->created_at->toIso8601String(),
                    ],
                ],
            ], 201);
        });
    }

    public function show(Request $request, PaymentIntent $paymentIntent): JsonResponse
    {
        abort_unless(
            $paymentIntent->order->patient_user_id === $request->user()->id ||
            $request->user()->can('view', $paymentIntent),
            404
        );

        $paymentIntent->load(['order', 'paymentTransactions']);

        return response()->json(['data' => [
            'payment_intent' => [
                'id' => $paymentIntent->id,
                'order_id' => $paymentIntent->order_id,
                'order_reference' => $paymentIntent->order?->public_reference,
                'gateway' => $paymentIntent->gateway,
                'gateway_reference' => $paymentIntent->gateway_reference,
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
                'status' => $paymentIntent->status,
                'payment_method' => $paymentIntent->payment_method,
                'payment_url' => $paymentIntent->payment_url,
                'callback_url' => $paymentIntent->callback_url,
                'failure_code' => $paymentIntent->failure_code,
                'failure_message' => $paymentIntent->failure_message,
                'gateway_response' => $paymentIntent->gateway_response,
                'created_at' => $paymentIntent->created_at->toIso8601String(),
                'processed_at' => $paymentIntent->processed_at?->toIso8601String(),
                'expires_at' => $paymentIntent->expires_at->toIso8601String(),
                'transactions' => $paymentIntent->paymentTransactions->map(fn ($t) => [
                    'id' => $t->id,
                    'gateway_reference' => $t->gateway_reference,
                    'transaction_type' => $t->transaction_type,
                    'status' => $t->status,
                    'amount' => $t->amount,
                    'currency' => $t->currency,
                    'processed_at' => $t->processed_at->toIso8601String(),
                    'settled_at' => $t->settled_at?->toIso8601String(),
                ]),
            ],
        ]]);
    }

    public function callback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gateway' => ['required', 'string'],
            'event' => ['required', 'string'],
            'signature' => ['required', 'string'],
            'payload' => ['required', 'array'],
        ]);

        try {
            $transaction = $this->paymentService->handleWebhook(
                $data['gateway'],
                $data['payload'],
                $data['signature']
            );

            // Record in ledger
            if ($transaction->status === 'succeeded') {
                $this->ledgerService->recordPayment($transaction);
            }

            // Send notification
            if ($transaction->order) {
                $this->outbox->record(
                    'payment.processed',
                    PaymentTransaction::class,
                    $transaction->id,
                    [
                        'template_key' => 'payment_' . $transaction->status,
                        'order_reference' => $transaction->order->public_reference,
                        'amount' => $transaction->amount,
                        'currency' => $transaction->currency,
                        'gateway' => $transaction->gateway,
                        'status' => $transaction->status,
                    ],
                    'payment.processed.' . $transaction->id,
                    $transaction->order->patient->locale ?? 'fa'
                );
            }

            return response()->json([
                'data' => [
                    'status' => 'processed',
                    'transaction_id' => $transaction->id,
                ],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'error' => [
                    'code' => 'webhook_error',
                    'message' => $exception->getMessage(),
                ],
            ], 400);
        }
    }

    public function createRefund(Request $request, Order $order): JsonResponse
    {
        abort_unless(
            $request->user()->can('create', Refund::class),
            404
        );

        $data = $request->validate([
            'payment_transaction_id' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($request, $order, $data): JsonResponse {
            $transaction = $order->paymentTransactions()
                ->where('id', $data['payment_transaction_id'])
                ->firstOrFail();

            // Check if refund amount exceeds transaction amount
            if ($data['amount'] > $transaction->amount) {
                abort(422, 'Refund amount cannot exceed transaction amount');
            }

            // Create refund
            $refund = Refund::query()->create([
                'id' => (string) Str::ulid(),
                'order_id' => $order->id,
                'payment_transaction_id' => $transaction->id,
                'initiated_by_user_id' => $request->user()->id,
                'reason' => $data['reason'],
                'status' => 'requested',
                'amount' => $data['amount'],
                'currency' => $transaction->currency,
                'gateway_reference' => null,
                'notes' => $data['notes'],
                'gateway_response' => null,
                'requested_at' => now(),
                'completed_at' => null,
            ]);

            // Record in ledger
            $this->ledgerService->recordRefund($refund);

            // Send notification
            $this->outbox->record(
                'refund.created',
                Refund::class,
                $refund->id,
                [
                    'template_key' => 'refund_requested',
                    'order_reference' => $order->public_reference,
                    'amount' => $refund->amount,
                    'currency' => $refund->currency,
                    'reason' => $refund->reason,
                ],
                'refund.created.' . $refund->id,
                $order->patient->locale ?? 'fa'
            );

            return response()->json(['data' => [
                'refund' => [
                    'id' => $refund->id,
                    'order_id' => $refund->order_id,
                    'payment_transaction_id' => $refund->payment_transaction_id,
                    'amount' => $refund->amount,
                    'currency' => $refund->currency,
                    'status' => $refund->status,
                    'reason' => $refund->reason,
                    'notes' => $refund->notes,
                    'requested_at' => $refund->requested_at->toIso8601String(),
                ],
            ]], 201);
        });
    }

    public function processRefund(Request $request, Refund $refund): JsonResponse
    {
        abort_unless(
            $request->user()->can('process', $refund),
            404
        );

        return DB::transaction(function () use ($request, $refund): JsonResponse {
            $result = $this->paymentService->processRefund($refund->paymentTransaction);

            if ($result) {
                $refund->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                // Send notification
                $this->outbox->record(
                    'refund.processed',
                    Refund::class,
                    $refund->id,
                    [
                        'template_key' => 'refund_completed',
                        'order_reference' => $refund->order->public_reference,
                        'amount' => $refund->amount,
                        'currency' => $refund->currency,
                    ],
                    'refund.processed.' . $refund->id,
                    $refund->order->patient->locale ?? 'fa'
                );
            }

            return response()->json(['data' => [
                'refund' => [
                    'id' => $refund->id,
                    'status' => $refund->status,
                    'completed_at' => $refund->completed_at?->toIso8601String(),
                ],
                'success' => $result,
            ]]);
        });
    }

    public function listTransactions(Request $request, Order $order): JsonResponse
    {
        abort_unless(
            $order->patient_user_id === $request->user()->id ||
            $request->user()->can('view', $order),
            404
        );

        $transactions = $order->paymentTransactions()
            ->with(['paymentIntent', 'refunds'])
            ->orderBy('processed_at', 'desc')
            ->get();

        return response()->json(['data' => [
            'transactions' => $transactions->map(fn ($t) => [
                'id' => $t->id,
                'payment_intent_id' => $t->payment_intent_id,
                'gateway' => $t->gateway,
                'gateway_reference' => $t->gateway_reference,
                'transaction_type' => $t->transaction_type,
                'status' => $t->status,
                'amount' => $t->amount,
                'currency' => $t->currency,
                'source' => $t->source,
                'destination' => $t->destination,
                'gateway_data' => $t->gateway_data,
                'processed_at' => $t->processed_at->toIso8601String(),
                'settled_at' => $t->settled_at?->toIso8601String(),
                'refunds' => $t->refunds->map(fn ($r) => [
                    'id' => $r->id,
                    'amount' => $r->amount,
                    'status' => $r->status,
                    'reason' => $r->reason,
                    'requested_at' => $r->requested_at->toIso8601String(),
                    'completed_at' => $r->completed_at?->toIso8601String(),
                ]),
            ]),
        ]]);
    }

    public function getPaymentUrl(Request $request, PaymentIntent $paymentIntent): JsonResponse
    {
        abort_unless(
            $paymentIntent->order->patient_user_id === $request->user()->id ||
            $request->user()->can('view', $paymentIntent),
            404
        );

        $url = $this->paymentService->getPaymentUrl($paymentIntent);

        return response()->json(['data' => [
            'payment_url' => $url,
            'expires_at' => $paymentIntent->expires_at->toIso8601String(),
        ]]);
    }
}
