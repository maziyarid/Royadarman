<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementBatch extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'item_count' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function settlementItems(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }
}
