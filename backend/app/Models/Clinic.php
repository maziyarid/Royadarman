<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinic extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(ClinicBranch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(ClinicUser::class);
    }

    public function payoutAccounts(): HasMany
    {
        return $this->hasMany(PayoutAccount::class);
    }

    public function credentialing(): HasMany
    {
        return $this->hasMany(ClinicCredentialing::class);
    }

    public function documentVerifications(): HasMany
    {
        return $this->hasMany(DocumentVerification::class);
    }

    public function referralProposals(): HasMany
    {
        return $this->hasMany(ReferralProposal::class);
    }

    public function referralGrants(): HasMany
    {
        return $this->hasMany(ReferralGrant::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'clinic_id');
    }

    public function settlementItems(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function supportCases(): HasMany
    {
        return $this->hasMany(SupportCase::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
