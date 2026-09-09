<?php

namespace App\Domain\Finance\Contracts;

use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;

interface PaymentService
{
    /**
     * Create a payment intent for an order
     */
    public function createPaymentIntent(Order $order, string $gateway, array $options = []): PaymentIntent;

    /**
     * Process a payment intent
     */
    public function processPaymentIntent(PaymentIntent $intent, array $paymentData): PaymentTransaction;

    /**
     * Handle payment gateway webhook
     */
    public function handleWebhook(string $gateway, array $payload, string $signature): PaymentTransaction;

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $gateway, array $payload, string $signature): bool;

    /**
     * Create a refund
     */
    public function createRefund(PaymentTransaction $transaction, int $amount, string $reason): PaymentTransaction;

    /**
     * Process a refund
     */
    public function processRefund(PaymentTransaction $transaction): bool;

    /**
     * Get payment gateway URL
     */
    public function getPaymentUrl(PaymentIntent $intent): string;

    /**
     * Check payment intent status
     */
    public function checkIntentStatus(PaymentIntent $intent): PaymentIntent;
}
