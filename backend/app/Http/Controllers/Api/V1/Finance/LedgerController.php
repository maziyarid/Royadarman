<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domain\Finance\Services\LedgerService;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class LedgerController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    public function listAccounts(Request $request): JsonResponse
    {
        $accounts = LedgerAccount::query()
            ->orderBy('code')
            ->get();

        return response()->json(['data' => [
            'accounts' => $accounts->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'type' => $a->type,
                'category' => $a->category,
                'currency' => $a->currency,
                'is_active' => $a->is_active,
                'description' => $a->description,
                'balance' => $this->ledgerService->getAccountBalance($a->code, $a->currency),
            ]),
        ]]);
    }

    public function showAccount(Request $request, LedgerAccount $ledgerAccount): JsonResponse
    {
        $balance = $this->ledgerService->getAccountBalance($ledgerAccount->code, $ledgerAccount->currency);

        $transactions = LedgerTransaction::query()
            ->whereHas('ledgerEntries', fn ($q) => $q->where('ledger_account_id', $ledgerAccount->id))
            ->with(['ledgerEntries'])
            ->orderBy('transaction_date', 'desc')
            ->limit(50)
            ->get();

        return response()->json(['data' => [
            'account' => [
                'id' => $ledgerAccount->id,
                'code' => $ledgerAccount->code,
                'name' => $ledgerAccount->name,
                'type' => $ledgerAccount->type,
                'category' => $ledgerAccount->category,
                'currency' => $ledgerAccount->currency,
                'is_active' => $ledgerAccount->is_active,
                'description' => $ledgerAccount->description,
                'balance' => $balance,
            ],
            'recent_transactions' => $transactions->map(fn ($t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'description' => $t->description,
                'transaction_date' => $t->transaction_date->toIso8601String(),
                'status' => $t->status,
                'total_debits' => $t->ledgerEntries
                    ->where('entry_type', 'debit')
                    ->sum('amount'),
                'total_credits' => $t->ledgerEntries
                    ->where('entry_type', 'credit')
                    ->sum('amount'),
            ]),
        ]]);
    }

    public function listTransactions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'account_id' => ['nullable', 'string'],
            'limit' => ['integer', 'min:1', 'max:100'],
        ]);

        $query = LedgerTransaction::query()
            ->with(['ledgerEntries.ledgerAccount'])
            ->orderBy('transaction_date', 'desc');

        if (isset($data['start_date'])) {
            $query->whereDate('transaction_date', '>=', $data['start_date']);
        }

        if (isset($data['end_date'])) {
            $query->whereDate('transaction_date', '<=', $data['end_date']);
        }

        if (isset($data['account_id'])) {
            $query->whereHas('ledgerEntries', fn ($q) => $q->where('ledger_account_id', $data['account_id']));
        }

        $transactions = $query->limit($data['limit'] ?? 50)->get();

        return response()->json(['data' => [
            'transactions' => $transactions->map(fn ($t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'description' => $t->description,
                'transaction_date' => $t->transaction_date->toIso8601String(),
                'posted_at' => $t->posted_at?->toIso8601String(),
                'status' => $t->status,
                'metadata' => $t->metadata,
                'entries' => $t->ledgerEntries->map(fn ($e) => [
                    'id' => $e->id,
                    'ledger_account_id' => $e->ledger_account_id,
                    'account_code' => $e->ledgerAccount?->code,
                    'account_name' => $e->ledgerAccount?->name,
                    'entry_type' => $e->entry_type,
                    'amount' => $e->amount,
                    'currency' => $e->currency,
                    'description' => $e->description,
                    'sequence' => $e->sequence,
                ]),
                'total_debits' => $t->ledgerEntries->where('entry_type', 'debit')->sum('amount'),
                'total_credits' => $t->ledgerEntries->where('entry_type', 'credit')->sum('amount'),
            ]),
        ]]);
    }

    public function getClinicBalance(Request $request, Clinic $clinic): JsonResponse
    {
        $balance = $this->ledgerService->getClinicBalance($clinic->id);

        return response()->json(['data' => [
            'clinic_id' => $clinic->id,
            'clinic_name' => $clinic->name,
            'balance' => $balance,
            'currency' => 'IRR',
        ]]);
    }

    public function reconcile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gateway' => ['required', 'in:zarinpal,idpay'],
            'date' => ['required', 'date'],
        ]);

        $result = $this->ledgerService->reconcile($data['gateway'], $data['date']);

        return response()->json(['data' => $result]);
    }

    public function createTransaction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:200'],
            'entries' => ['required', 'array', 'min:2'],
            'entries.*.account_code' => ['required', 'string'],
            'entries.*.entry_type' => ['required', Rule::in(['debit', 'credit'])],
            'entries.*.amount' => ['required', 'integer', 'min:1'],
            'entries.*.description' => ['nullable', 'string'],
        ]);

        $transaction = $this->ledgerService->createTransaction(
            $data['reference'],
            $data['description'],
            $data['entries']
        );

        return response()->json(['data' => [
            'transaction' => [
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'description' => $transaction->description,
                'transaction_date' => $transaction->transaction_date->toIso8601String(),
                'status' => $transaction->status,
            ],
        ]], 201);
    }
}
