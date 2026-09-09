<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityRule extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'effective_date' => 'date',
            'expires_date' => 'date',
            'duration_minutes' => 'integer',
            'max_appointments' => 'integer',
        ];
    }

    public function clinicBranch(): BelongsTo
    {
        return $this->belongsTo(ClinicBranch::class);
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(Dentist::class);
    }
}
