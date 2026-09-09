<?php

namespace App\Domain\Finance\Contracts;

use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;

interface LedgerService
{
    /**
     * Record a payment transaction in the ledger
     */
    public function recordPayment(PaymentTransaction $transaction): LedgerTransaction;

    /**
     * Record a refund in the ledger
     */
    public function recordRefund(Refund $refund): LedgerTransaction;

    /**
     * Record an order in the ledger
     */
    public function recordOrder(Order $order): LedgerTransaction;

    /**
     * Record a settlement
     */
    public function recordSettlement(array $settlementData): LedgerTransaction;

    /**
     * Record a platform fee
     */
    public function recordPlatformFee(PaymentTransaction $transaction, int $feeAmount): LedgerTransaction;

    /**
     * Get account balance
     */
    public function getAccountBalance(string $accountCode, string $currency = 'IRR'): int;

    /**
     * Get ledger balance for a clinic
     */
    public function getClinicBalance(string $clinicId, string $currency = 'IRR'): int;

    /**
     * Reconcile ledger with payment gateway
     */
    public function reconcile(string $gateway, string $date): array;

    /**
     * Create a ledger transaction with entries
     */
    public function createTransaction(string $reference, string $description, array $entries): LedgerTransaction;
}
