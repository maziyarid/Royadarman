<?php

namespace App\Models;

use App\Support\PanelDemoRegistry;
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

    public function isSyntheticDemo(): bool
    {
        return $this->synthetic_demo_key === PanelDemoRegistry::CLINIC_DEMO_KEY;
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ClinicMembership::class);
    }

    public function referralProposals(): HasMany
    {
        return $this->hasMany(ReferralProposal::class);
    }

    public function referralGrants(): HasMany
    {
        return $this->hasMany(ReferralGrant::class);
    }

    public function activeMemberships(): HasMany
    {
        $now = now();

        return $this->hasMany(ClinicMembership::class)
            ->where('active_from', '<=', $now)
            ->where(fn ($q) => $q->whereNull('active_until')->orWhere('active_until', '>', $now));
    }
}
