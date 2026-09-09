<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function complainant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'complainant_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(Dentist::class);
    }
}
