<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicBranch extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:10,8',
            'longitude' => 'decimal:11,8',
            'is_active' => 'boolean',
            'accepts_home_visits' => 'boolean',
            'accepts_emergency' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function clinicUsers(): HasMany
    {
        return $this->hasMany(ClinicUser::class);
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(OperatingHour::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    public function bookingModes(): HasMany
    {
        return $this->hasMany(BookingMode::class);
    }

    public function availabilityRules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class);
    }

    public function capacityWindows(): HasMany
    {
        return $this->hasMany(CapacityWindow::class);
    }

    public function appointmentSlots(): HasMany
    {
        return $this->hasMany(AppointmentSlot::class);
    }

    public function branchServices(): HasMany
    {
        return $this->hasMany(BranchService::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function matchCandidates(): HasMany
    {
        return $this->hasMany(MatchCandidate::class);
    }
}
