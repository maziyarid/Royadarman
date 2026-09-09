<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'sequence' => 'integer',
        ];
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
