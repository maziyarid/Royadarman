<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentTransaction extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'processed_at' => 'datetime',
            'settled_at' => 'datetime',
            'gateway_data' => 'json',
        ];
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function paymentWebhooks(): HasMany
    {
        return $this->hasMany(PaymentWebhook::class);
    }

    public function settlementItems(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }
}
