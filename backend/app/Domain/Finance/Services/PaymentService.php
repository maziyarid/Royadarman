<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Contracts\PaymentService as PaymentServiceContract;
use App\Domain\Finance\Enums\PaymentStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentService implements PaymentServiceContract
{
    private const GATEWAY_CONFIG = [
        'zarinpal' => [
            'merchant_id' => 'ZARINPAL_MERCHANT_ID',
            'callback_url' => 'ZARINPAL_CALLBACK_URL',
            'currency' => 'IRR',
        ],
        'idpay' => [
            'api_key' => 'IDPAY_API_KEY',
            'sandbox' => true,
            'currency' => 'IRR',
        ],
    ];

    public function createPaymentIntent(Order $order, string $gateway, array $options = []): PaymentIntent
    {
        return DB::transaction(function () use ($order, $gateway, $options): PaymentIntent {
            $gatewayConfig = self::GATEWAY_CONFIG[$gateway] ?? [];

            $intent = PaymentIntent::query()->create([
                'id' => (string) Str::ulid(),
                'order_id' => $order->id,
                'gateway' => $gateway,
                'gateway_reference' => null,
                'status' => PaymentStatus::Pending->value,
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'payment_method' => $options['payment_method'] ?? 'ipg',
                'payment_url' => null,
                'callback_url' => $options['callback_url'] ?? route('api.v1.payments.callback'),
                'gateway_response' => null,
                'failure_code' => null,
                'failure_message' => null,
                'created_at' => now(),
                'processed_at' => null,
                'expires_at' => now()->addHours(24),
            ]);

            // Generate payment URL based on gateway
            $intent->payment_url = $this->generateGatewayUrl($intent, $gatewayConfig);
            $intent->save();

            return $intent;
        });
    }

    public function processPaymentIntent(PaymentIntent $intent, array $paymentData): PaymentTransaction
    {
        return DB::transaction(function () use ($intent, $paymentData): PaymentTransaction {
            $gatewayReference = $paymentData['gateway_reference'] ?? (string) Str::ulid();

            $transaction = PaymentTransaction::query()->create([
                'id' => (string) Str::ulid(),
                'payment_intent_id' => $intent->id,
                'order_id' => $intent->order_id,
                'gateway' => $intent->gateway,
                'gateway_reference' => $gatewayReference,
                'transaction_type' => TransactionType::Authorization->value,
                'status' => PaymentStatus::Pending->value,
                'amount' => $intent->amount,
                'currency' => $intent->currency,
                'source' => 'patient',
                'destination' => 'platform',
                'gateway_data' => json_encode($paymentData),
                'processed_at' => now(),
                'settled_at' => null,
            ]);

            // Update intent status
            $intent->update([
                'gateway_reference' => $gatewayReference,
                'status' => PaymentStatus::Processing->value,
                'gateway_response' => json_encode($paymentData),
                'processed_at' => now(),
            ]);

            return $transaction;
        });
    }

    public function handleWebhook(string $gateway, array $payload, string $signature): PaymentTransaction
    {
        return DB::transaction(function () use ($gateway, $payload, $signature): PaymentTransaction {
            // Verify signature
            if (! $this->verifyWebhookSignature($gateway, $payload, $signature)) {
                throw new \RuntimeException('Invalid webhook signature');
            }

            // Store the webhook
            $webhook = PaymentWebhook::query()->create([
                'id' => (string) Str::ulid(),
                'gateway' => $gateway,
                'event_type' => $payload['event'] ?? 'unknown',
                'signature' => $signature,
                'payload' => json_encode($payload),
                'status' => 'received',
                'failure_reason' => null,
                'ip_hash' => null,
                'received_at' => now(),
                'processed_at' => null,
            ]);

            // Find the intent
            $intent = PaymentIntent::query()
                ->where('gateway', $gateway)
                ->where('gateway_reference', $payload['reference'] ?? $payload['authority'] ?? null)
                ->first();

            if (! $intent) {
                $webhook->update(['status' => 'failed', 'failure_reason' => 'intent_not_found']);
                throw new \RuntimeException('Payment intent not found');
            }

            // Process based on event type
            $eventType = $payload['event'] ?? $payload['status'] ?? '';
            $transaction = $this->processWebhookEvent($intent, $eventType, $payload);

            // Update webhook
            $webhook->update([
                'payment_intent_id' => $intent->id,
                'payment_transaction_id' => $transaction->id,
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            return $transaction;
        });
    }

    public function verifyWebhookSignature(string $gateway, array $payload, string $signature): bool
    {
        // Get gateway secret
        $secret = config("royadarman.payment_gateways.{$gateway}.webhook_secret");

        if (! $secret) {
            return false;
        }

        // Generate expected signature
        $data = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $expected = hash_hmac('sha256', $data, $secret);

        return hash_equals($expected, $signature);
    }

    public function createRefund(PaymentTransaction $transaction, int $amount, string $reason): PaymentTransaction
    {
        return DB::transaction(function () use ($transaction, $amount, $reason): PaymentTransaction {
            $refundTransaction = PaymentTransaction::query()->create([
                'id' => (string) Str::ulid(),
                'payment_intent_id' => $transaction->payment_intent_id,
                'order_id' => $transaction->order_id,
                'gateway' => $transaction->gateway,
                'gateway_reference' => $transaction->gateway_reference . '-REFUND',
                'transaction_type' => TransactionType::Refund->value,
                'status' => PaymentStatus::Pending->value,
                'amount' => $amount,
                'currency' => $transaction->currency,
                'source' => 'platform',
                'destination' => 'patient',
                'gateway_data' => json_encode(['refund_reason' => $reason]),
                'processed_at' => now(),
                'settled_at' => null,
            ]);

            // Update original transaction
            $transaction->update(['status' => PaymentStatus::Refunded->value]);

            return $refundTransaction;
        });
    }

    public function processRefund(PaymentTransaction $transaction): bool
    {
        if ($transaction->transaction_type !== TransactionType::Refund->value) {
            return false;
        }

        return DB::transaction(function () use ($transaction): bool {
            // Call gateway API to process refund
            $result = $this->callGatewayRefundApi($transaction);

            if ($result) {
                $transaction->update([
                    'status' => PaymentStatus::Succeeded->value,
                    'settled_at' => now(),
                ]);
            } else {
                $transaction->update(['status' => PaymentStatus::Failed->value]);
            }

            return $result;
        });
    }

    public function getPaymentUrl(PaymentIntent $intent): string
    {
        if ($intent->payment_url) {
            return $intent->payment_url;
        }

        return $this->generateGatewayUrl($intent, self::GATEWAY_CONFIG[$intent->gateway] ?? []);
    }

    public function checkIntentStatus(PaymentIntent $intent): PaymentIntent
    {
        // Call gateway API to check status
        $status = $this->callGatewayStatusApi($intent);

        if ($status) {
            $intent->update([
                'status' => $status['status'],
                'gateway_response' => json_encode($status),
                'processed_at' => now(),
            ]);
        }

        return $intent;
    }

    /**
     * Generate payment gateway URL
     */
    private function generateGatewayUrl(PaymentIntent $intent, array $gatewayConfig): string
    {
        $gateway = $intent->gateway;

        switch ($gateway) {
            case 'zarinpal':
                return $this->generateZarinpalUrl($intent, $gatewayConfig);

            case 'idpay':
                return $this->generateIdpayUrl($intent, $gatewayConfig);

            default:
                throw new \RuntimeException("Unsupported payment gateway: {$gateway}");
        }
    }

    /**
     * Generate Zarinpal payment URL
     */
    private function generateZarinpalUrl(PaymentIntent $intent, array $config): string
    {
        $merchantId = config('royadarman.payment_gateways.zarinpal.merchant_id', $config['merchant_id'] ?? '');
        $callbackUrl = config('royadarman.payment_gateways.zarinpal.callback_url', $config['callback_url'] ?? '');

        // Generate authority
        $authority = 'AUTH-' . Str::upper(Str::random(16));

        // In production, you would call Zarinpal API here
        // For now, return a placeholder URL
        return "https://www.zarinpal.com/pg/StartPay/{$authority}/ZarinGate";
    }

    /**
     * Generate IDPay payment URL
     */
    private function generateIdpayUrl(PaymentIntent $intent, array $config): string
    {
        $apiKey = config('royadarman.payment_gateways.idpay.api_key', $config['api_key'] ?? '');
        $sandbox = config('royadarman.payment_gateways.idpay.sandbox', $config['sandbox'] ?? true);

        // In production, you would call IDPay API here
        // For now, return a placeholder URL
        $orderId = $intent->order->public_reference;
        $amount = $intent->amount;

        return $sandbox
            ? "https://sandbox.idpay.ir/payment/webgate/{$orderId}/{$amount}"
            : "https://idpay.ir/payment/webgate/{$orderId}/{$amount}";
    }

    /**
     * Process webhook event
     */
    private function processWebhookEvent(PaymentIntent $intent, string $eventType, array $payload): PaymentTransaction
    {
        $status = match (strtolower($eventType)) {
            'success', 'paid', 'succeeded', 'verified' => PaymentStatus::Succeeded,
            'failed', 'error', 'rejected' => PaymentStatus::Failed,
            'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Processing,
        };

        $transactionType = match (strtolower($eventType)) {
            'refunded' => TransactionType::Refund,
            'chargeback' => TransactionType::Chargeback,
            default => TransactionType::Capture,
        };

        $transaction = PaymentTransaction::query()->create([
            'id' => (string) Str::ulid(),
            'payment_intent_id' => $intent->id,
            'order_id' => $intent->order_id,
            'gateway' => $intent->gateway,
            'gateway_reference' => $payload['reference'] ?? $payload['authority'] ?? $intent->gateway_reference,
            'transaction_type' => $transactionType->value,
            'status' => $status->value,
            'amount' => $payload['amount'] ?? $intent->amount,
            'currency' => $intent->currency,
            'source' => 'patient',
            'destination' => 'platform',
            'gateway_data' => json_encode($payload),
            'processed_at' => now(),
            'settled_at' => $status === PaymentStatus::Succeeded ? now() : null,
        ]);

        // Update intent
        $intent->update([
            'status' => $status->value,
            'gateway_response' => json_encode($payload),
            'processed_at' => now(),
        ]);

        // Update order if payment succeeded
        if ($status === PaymentStatus::Succeeded) {
            $intent->order()->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        return $transaction;
    }

    /**
     * Call gateway refund API
     */
    private function callGatewayRefundApi(PaymentTransaction $transaction): bool
    {
        // Placeholder - implement actual API call
        return true;
    }

    /**
     * Call gateway status API
     */
    private function callGatewayStatusApi(PaymentIntent $intent): ?array
    {
        // Placeholder - implement actual API call
        return null;
    }
}
