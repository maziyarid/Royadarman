<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'duration_minutes' => 'integer',
            'visit_fee' => 'integer',
            'booking_mode' => 'string',
        ];
    }

    public function clinicBranch(): BelongsTo
    {
        return $this->belongsTo(ClinicBranch::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(Dentist::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function slotHold(): BelongsTo
    {
        return $this->belongsTo(SlotHold::class);
    }

    public function appointmentSlot(): BelongsTo
    {
        return $this->belongsTo(AppointmentSlot::class);
    }

    public function capacityWindow(): BelongsTo
    {
        return $this->belongsTo(CapacityWindow::class);
    }

    public function referralRequest(): BelongsTo
    {
        return $this->belongsTo(ReferralRequest::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function visitConfirmations(): HasMany
    {
        return $this->hasMany(VisitConfirmation::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function supportCases(): HasMany
    {
        return $this->hasMany(SupportCase::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function settlementItems(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }
}
