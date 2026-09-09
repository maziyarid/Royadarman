<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ReviewRevision extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['image_adequacy' => 'encrypted', 'observations' => 'encrypted', 'limitations' => 'encrypted', 'options' => 'encrypted', 'recommended_next_step' => 'encrypted', 'signed_at' => 'immutable_datetime'];
    }
}
