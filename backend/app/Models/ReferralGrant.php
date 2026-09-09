<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ReferralGrant extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scope' => 'array', 'granted_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}

