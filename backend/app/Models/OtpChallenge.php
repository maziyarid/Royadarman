<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class OtpChallenge extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['code_hash', 'phone'];

    protected function casts(): array
    {
        return ['phone' => 'encrypted', 'expires_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime', 'last_sent_at' => 'immutable_datetime'];
    }
}
