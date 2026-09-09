<?php

namespace App\Models;

use App\Domain\Identity\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'locale', 'phone', 'phone_hash', 'totp_secret', 'mfa_recovery_codes', 'is_active'])]
#[Hidden(['password', 'remember_token', 'phone', 'totp_secret', 'mfa_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'phone' => 'encrypted',
            'totp_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'is_active' => 'boolean',
            'last_authenticated_at' => 'immutable_datetime',
        ];
    }

    public function practitioner(): HasOne
    {
        return $this->hasOne(Practitioner::class, 'user_id');
    }

    public function clinicMemberships(): HasMany
    {
        return $this->hasMany(ClinicMembership::class, 'user_id');
    }

    public function reviewRevisions(): HasMany
    {
        return $this->hasMany(ReviewRevision::class, 'clinician_user_id');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(PatientCase::class, 'patient_user_id');
    }

    public function coordinatedCases(): HasMany
    {
        return $this->hasMany(PatientCase::class, 'current_coordinator_id');
    }

    public function referralProposals(): HasMany
    {
        return $this->hasMany(ReferralProposal::class, 'proposed_by_user_id');
    }

    public function consentRecords(): HasMany
    {
        return $this->hasMany(ConsentRecord::class, 'subject_user_id');
    }
}
