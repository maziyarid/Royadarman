<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'expected_arrival_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by_user_id');
    }
}
