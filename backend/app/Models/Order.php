<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'submitted_at' => 'datetime',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function referralRequest(): BelongsTo
    {
        return $this->belongsTo(ReferralRequest::class);
    }

    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function priceSnapshots(): HasMany
    {
        return $this->hasMany(PriceSnapshot::class);
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function paymentWebhooks(): HasMany
    {
        return $this->hasMany(PaymentWebhook::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function settlementItems(): HasMany
    {
        return $this->hasMany(SettlementItem::class, 'appointment_id');
    }
}
