<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayoutAccount extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['account_number', 'sheba_code'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
            'account_number' => 'encrypted',
            'sheba_code' => 'encrypted',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function settlementItems(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }
}
