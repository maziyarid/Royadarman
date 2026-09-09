<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SlotHold extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'converted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function appointmentSlot(): BelongsTo
    {
        return $this->belongsTo(AppointmentSlot::class);
    }

    public function referralRequest(): BelongsTo
    {
        return $this->belongsTo(ReferralRequest::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class);
    }
}
