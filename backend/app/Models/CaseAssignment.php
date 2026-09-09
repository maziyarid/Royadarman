<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CaseAssignment extends Model
{
    use HasUlids;

    protected $table = 'case_assignments';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['assigned_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime'];
    }
}

