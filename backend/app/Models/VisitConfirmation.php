<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitConfirmation extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'requires_follow_up' => 'boolean',
            'follow_up_due_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }
}
