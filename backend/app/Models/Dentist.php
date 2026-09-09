<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dentist extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['national_id', 'medical_license_number'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'national_id' => 'encrypted',
            'medical_license_number' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }

    public function clinicMemberships(): HasMany
    {
        return $this->hasMany(ClinicMembership::class);
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

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function documentVerifications(): HasMany
    {
        return $this->hasMany(DocumentVerification::class);
    }
}
