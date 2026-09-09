<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerTransaction extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime',
            'posted_at' => 'datetime',
            'metadata' => 'json',
        ];
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
