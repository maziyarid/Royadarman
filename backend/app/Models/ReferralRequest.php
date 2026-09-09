<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralRequest extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['patient_mobile'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:10,8',
            'longitude' => 'decimal:11,8',
            'budget_amount' => 'integer',
            'submitted_at' => 'datetime',
            'expires_at' => 'datetime',
            'matched_at' => 'datetime',
            'closed_at' => 'datetime',
            'preferences' => 'json',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class);
    }

    public function intakeAnswers(): HasMany
    {
        return $this->hasMany(IntakeAnswer::class);
    }

    public function urgencyAssessments(): HasMany
    {
        return $this->hasMany(UrgencyAssessment::class);
    }

    public function patientPreferences(): HasMany
    {
        return $this->hasMany(PatientPreference::class);
    }

    public function matchRuns(): HasMany
    {
        return $this->hasMany(MatchRun::class);
    }

    public function matchDecisions(): HasMany
    {
        return $this->hasMany(MatchDecision::class);
    }

    public function referralProposals(): HasMany
    {
        return $this->hasMany(ReferralProposal::class);
    }

    public function slotHolds(): HasMany
    {
        return $this->hasMany(SlotHold::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
