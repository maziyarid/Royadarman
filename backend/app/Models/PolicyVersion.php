<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PolicyVersion extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime'];
    }
}
