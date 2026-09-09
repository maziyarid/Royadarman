<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentIntent extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['callback_url'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'datetime',
            'processed_at' => 'datetime',
            'expires_at' => 'datetime',
            'gateway_response' => 'json',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function paymentWebhooks(): HasMany
    {
        return $this->hasMany(PaymentWebhook::class);
    }
}
