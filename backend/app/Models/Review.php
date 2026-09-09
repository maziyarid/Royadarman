<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'ratings_breakdown' => 'json',
            'is_verified' => 'boolean',
            'is_anonymous' => 'boolean',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
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
