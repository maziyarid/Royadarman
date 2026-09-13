<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralGrant extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'granted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ReferralProposal::class, 'proposal_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinic_id');
    }
}
