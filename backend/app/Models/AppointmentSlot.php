<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentSlot extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'duration_minutes' => 'integer',
            'price' => 'integer',
            'is_recurring' => 'boolean',
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function slotHolds(): HasMany
    {
        return $this->hasMany(SlotHold::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
