<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ReferralProposal extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['reasoning' => 'encrypted', 'proposed_at' => 'immutable_datetime', 'withdrawn_at' => 'immutable_datetime', 'decided_at' => 'immutable_datetime'];
    }
}

