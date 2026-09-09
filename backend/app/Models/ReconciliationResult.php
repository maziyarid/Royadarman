<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ReconciliationResult extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'integer',
            'actual_amount' => 'integer',
            'discrepancy_amount' => 'integer',
            'transaction_count' => 'integer',
            'matched_count' => 'integer',
            'discrepancy_count' => 'integer',
            'reconciled_at' => 'datetime',
            'discrepancies' => 'json',
        ];
    }
}
