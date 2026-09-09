<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhook extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['signature', 'ip_hash'];

    protected function casts(): array
    {
        return [
            'payload' => 'json',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }
}
