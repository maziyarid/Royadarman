<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Contracts\LedgerService as LedgerServiceContract;
use App\Domain\Finance\Enums\EntryType;
use App\Domain\Finance\Enums\LedgerAccountType;
use App\Domain\Finance\Enums\TransactionType;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LedgerService implements LedgerServiceContract
{
    // Default ledger accounts
    private const DEFAULT_ACCOUNTS = [
        'cash' => [
            'code' => '1000',
            'name' => 'Cash',
            'type' => LedgerAccountType::Asset->value,
            'category' => 'cash',
        ],
        'accounts_receivable' => [
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'type' => LedgerAccountType::Asset->value,
            'category' => 'receivable',
        ],
        'accounts_payable' => [
            'code' => '2000',
            'name' => 'Accounts Payable',
            'type' => LedgerAccountType::Liability->value,
            'category' => 'payable',
        ],
        'revenue' => [
            'code' => '4000',
            'name' => 'Revenue',
            'type' => LedgerAccountType::Revenue->value,
            'category' => 'revenue',
        ],
        'platform_fee' => [
            'code' => '4100',
            'name' => 'Platform Fee Revenue',
            'type' => LedgerAccountType::Revenue->value,
            'category' => 'fee',
        ],
        'clinic_settlement' => [
            'code' => '2100',
            'name' => 'Clinic Settlement Payable',
            'type' => LedgerAccountType::Liability->value,
            'category' => 'payable',
        ],
    ];

    public function recordPayment(PaymentTransaction $transaction): LedgerTransaction
    {
        return DB::transaction(function () use ($transaction): LedgerTransaction {
            // Get or create accounts
            $cashAccount = $this->getOrCreateAccount('cash');
            $receivableAccount = $this->getOrCreateAccount('accounts_receivable');

            $reference = 'PAY-' . Str::upper(Str::random(8));
            $description = "Payment from order {$transaction->order->public_reference}";

            $ledgerTransaction = LedgerTransaction::query()->create([
                'id' => (string) Str::ulid(),
                'reference' => $reference,
                'description' => $description,
                'transaction_date' => now(),
                'posted_at' => now(),
                'status' => 'posted',
                'metadata' => json_encode([
                    'payment_transaction_id' => $transaction->id,
                    'order_id' => $transaction->order_id,
                    'gateway' => $transaction->gateway,
                ]),
            ]);

            // Create entries
            $this->createEntry($ledgerTransaction, $cashAccount, EntryType::Debit, $transaction->amount, $description, $transaction);
            $this->createEntry($ledgerTransaction, $receivableAccount, EntryType::Credit, $transaction->amount, $description, $transaction);

            return $ledgerTransaction;
        });
    }

    public function recordRefund(Refund $refund): LedgerTransaction
    {
        return DB::transaction(function () use ($refund): LedgerTransaction {
            $cashAccount = $this->getOrCreateAccount('cash');
            $receivableAccount = $this->getOrCreateAccount('accounts_receivable');

            $reference = 'REF-' . Str::upper(Str::random(8));
            $description = "Refund for order {$refund->order->public_reference}";

            $ledgerTransaction = LedgerTransaction::query()->create([
                'id' => (string) Str::ulid(),
                'reference' => $reference,
                'description' => $description,
                'transaction_date' => now(),
                'posted_at' => now(),
                'status' => 'posted',
                'metadata' => json_encode([
                    'refund_id' => $refund->id,
                    'order_id' => $refund->order_id,
                    'reason' => $refund->reason,
                ]),
            ]);

            // Create entries (reverse of payment)
            $this->createEntry($ledgerTransaction, $cashAccount, EntryType::Credit, $refund->amount, $description, null, $refund);
            $this->createEntry($ledgerTransaction, $receivableAccount, EntryType::Debit, $refund->amount, $description, null, $refund);

            return $ledgerTransaction;
        });
    }

    public function recordOrder(Order $order): LedgerTransaction
    {
        return DB::transaction(function () use ($order): LedgerTransaction {
            $receivableAccount = $this->getOrCreateAccount('accounts_receivable');
            $revenueAccount = $this->getOrCreateAccount('revenue');

            $reference = 'ORD-' . $order->public_reference;
            $description = "Order {$order->public_reference} - {$order->type}";

            $ledgerTransaction = LedgerTransaction::query()->create([
                'id' => (string) Str::ulid(),
                'reference' => $reference,
                'description' => $description,
                'transaction_date' => $order->created_at ?? now(),
                'posted_at' => now(),
                'status' => 'posted',
                'metadata' => json_encode([
                    'order_id' => $order->id,
                    'type' => $order->type,
                ]),
            ]);

            // Create entries
            $this->createEntry($ledgerTransaction, $receivableAccount, EntryType::Debit, $order->total_amount, $description, null, null, $order);
            $this->createEntry($ledgerTransaction, $revenueAccount, EntryType::Credit, $order->total_amount, $description, null, null, $order);

            return $ledgerTransaction;
        });
    }

    public function recordSettlement(array $settlementData): LedgerTransaction
    {
        return DB::transaction(function () use ($settlementData): LedgerTransaction {
            $settlementAccount = $this->getOrCreateAccount('clinic_settlement');
            $cashAccount = $this->getOrCreateAccount('cash');

            $reference = 'SET-' . Str::upper(Str::random(8));
            $description = "Settlement to clinic {$settlementData['clinic_id']}";

            $ledgerTransaction = LedgerTransaction::query()->create([
                'id' => (string) Str::ulid(),
                'reference' => $reference,
                'description' => $description,
                'transaction_date' => now(),
                'posted_at' => now(),
                'status' => 'posted',
                'metadata' => json_encode($settlementData),
            ]);

            // Create entries
            $this->createEntry($ledgerTransaction, $settlementAccount, EntryType::Debit, $settlementData['net_amount'], $description, null, null, null, $settlementData['clinic_id']);
            $this->createEntry($ledgerTransaction, $cashAccount, EntryType::Credit, $settlementData['net_amount'], $description, null, null, null, $settlementData['clinic_id']);

            return $ledgerTransaction;
        });
    }

    public function recordPlatformFee(PaymentTransaction $transaction, int $feeAmount): LedgerTransaction
    {
        return DB::transaction(function () use ($transaction, $feeAmount): LedgerTransaction {
            $feeAccount = $this->getOrCreateAccount('platform_fee');
            $cashAccount = $this->getOrCreateAccount('cash');

            $reference = 'FEE-' . Str::upper(Str::random(8));
            $description = "Platform fee for order {$transaction->order->public_reference}";

            $ledgerTransaction = LedgerTransaction::query()->create([
                'id' => (string) Str::ulid(),
                'reference' => $reference,
                'description' => $description,
                'transaction_date' => now(),
                'posted_at' => now(),
                'status' => 'posted',
                'metadata' => json_encode([
                    'payment_transaction_id' => $transaction->id,
                    'fee_amount' => $feeAmount,
                ]),
            ]);

            // Create entries
            $this->createEntry($ledgerTransaction, $cashAccount, EntryType::Debit, $feeAmount, $description, $transaction);
            $this->createEntry($ledgerTransaction, $feeAccount, EntryType::Credit, $feeAmount, $description, $transaction);

            return $ledgerTransaction;
        });
    }

    public function getAccountBalance(string $accountCode, string $currency = 'IRR'): int
    {
        $account = LedgerAccount::query()
            ->where('code', $accountCode)
            ->firstOrFail();

        $debits = LedgerEntry::query()
            ->where('ledger_account_id', $account->id)
            ->where('entry_type', EntryType::Debit->value)
            ->where('currency', $currency)
            ->sum('amount');

        $credits = LedgerEntry::query()
            ->where('ledger_account_id', $account->id)
            ->where('entry_type', EntryType::Credit->value)
            ->where('currency', $currency)
            ->sum('amount');

        // For asset/expense accounts: debit - credit
        // For liability/equity/revenue accounts: credit - debit
        $balance = $debits - $credits;

        if ($account->type === LedgerAccountType::Liability->value ||
            $account->type === LedgerAccountType::Equity->value ||
            $account->type === LedgerAccountType::Revenue->value) {
            $balance = $credits - $debits;
        }

        return (int) $balance;
    }

    public function getClinicBalance(string $clinicId, string $currency = 'IRR'): int
    {
        $settlementAccount = $this->getOrCreateAccount('clinic_settlement');

        $debits = LedgerEntry::query()
            ->where('ledger_account_id', $settlementAccount->id)
            ->where('clinic_id', $clinicId)
            ->where('entry_type', EntryType::Debit->value)
            ->where('currency', $currency)
            ->sum('amount');

        $credits = LedgerEntry::query()
            ->where('ledger_account_id', $settlementAccount->id)
            ->where('clinic_id', $clinicId)
            ->where('entry_type', EntryType::Credit->value)
            ->where('currency', $currency)
            ->sum('amount');

        // Settlement account is a liability, so balance = credit - debit
        return (int) ($credits - $debits);
    }

    public function reconcile(string $gateway, string $date): array
    {
        // Get all transactions for this gateway and date
        $transactions = PaymentTransaction::query()
            ->where('gateway', $gateway)
            ->whereDate('processed_at', $date)
            ->where('status', 'succeeded')
            ->with('order')
            ->get();

        $totalAmount = $transactions->sum('amount');
        $count = $transactions->count();

        // Get ledger entries for these transactions
        $ledgerAmount = LedgerEntry::query()
            ->whereHas('paymentTransaction', fn ($q) => $q->where('gateway', $gateway)->whereDate('processed_at', $date))
            ->sum('amount');

        $discrepancy = $totalAmount - $ledgerAmount;

        return [
            'gateway' => $gateway,
            'date' => $date,
            'transaction_count' => $count,
            'gateway_total' => $totalAmount,
            'ledger_total' => $ledgerAmount,
            'discrepancy' => $discrepancy,
            'matched' => $discrepancy === 0,
        ];
    }

    public function createTransaction(string $reference, string $description, array $entries): LedgerTransaction
    {
        return DB::transaction(function () use ($reference, $description, $entries): LedgerTransaction {
            $ledgerTransaction = LedgerTransaction::query()->create([
                'id' => (string) Str::ulid(),
                'reference' => $reference,
                'description' => $description,
                'transaction_date' => now(),
                'posted_at' => now(),
                'status' => 'posted',
                'metadata' => null,
            ]);

            $sequence = 0;
            foreach ($entries as $entry) {
                $this->createEntry(
                    $ledgerTransaction,
                    $this->getOrCreateAccountByCode($entry['account_code']),
                    EntryType::from($entry['entry_type']),
                    $entry['amount'],
                    $entry['description'] ?? $description,
                    $entry['payment_transaction'] ?? null,
                    $entry['refund'] ?? null,
                    $entry['order'] ?? null,
                    $entry['clinic_id'] ?? null,
                    $sequence++
                );
            }

            return $ledgerTransaction;
        });
    }

    /**
     * Get or create a ledger account by key
     */
    private function getOrCreateAccount(string $key): LedgerAccount
    {
        if (! isset(self::DEFAULT_ACCOUNTS[$key])) {
            throw new \RuntimeException("Unknown ledger account: {$key}");
        }

        $config = self::DEFAULT_ACCOUNTS[$key];

        return LedgerAccount::query()->firstOrCreate(
            ['code' => $config['code']],
            [
                'name' => $config['name'],
                'type' => $config['type'],
                'category' => $config['category'],
                'currency' => 'IRR',
                'is_active' => true,
                'description' => null,
                'metadata' => null,
            ]
        );
    }

    /**
     * Get or create a ledger account by code
     */
    private function getOrCreateAccountByCode(string $code): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $code,
                'type' => LedgerAccountType::Asset->value,
                'category' => 'other',
                'currency' => 'IRR',
                'is_active' => true,
            ]
        );
    }

    /**
     * Create a ledger entry
     */
    private function createEntry(
        LedgerTransaction $transaction,
        LedgerAccount $account,
        EntryType $entryType,
        int $amount,
        string $description,
        ?PaymentTransaction $paymentTransaction = null,
        ?Refund $refund = null,
        ?Order $order = null,
        ?string $clinicId = null,
        int $sequence = 0
    ): LedgerEntry {
        return LedgerEntry::query()->create([
            'id' => (string) Str::ulid(),
            'ledger_transaction_id' => $transaction->id,
            'ledger_account_id' => $account->id,
            'entry_type' => $entryType->value,
            'amount' => $amount,
            'currency' => 'IRR',
            'description' => $description,
            'payment_transaction_id' => $paymentTransaction?->id,
            'refund_id' => $refund?->id,
            'order_id' => $order?->id,
            'patient_user_id' => $order?->patient_user_id,
            'clinic_id' => $clinicId,
            'sequence' => $sequence,
            'created_at' => now(),
        ]);
    }
}
