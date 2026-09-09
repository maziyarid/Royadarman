<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'available_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime'];
    }
}

