<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Refund extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'gateway_response' => 'json',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
