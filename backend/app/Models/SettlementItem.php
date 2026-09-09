<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementItem extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'platform_fee' => 'integer',
            'tax_amount' => 'integer',
            'net_amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function settlementBatch(): BelongsTo
    {
        return $this->belongsTo(SettlementBatch::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(PayoutAccount::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
