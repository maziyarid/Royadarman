<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerAccount extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'json',
        ];
    }

    public function ledgerTransactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
