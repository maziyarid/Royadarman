<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralProposal extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reasoning' => 'encrypted',
            'proposed_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinic_id');
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    public function grant()
    {
        return $this->hasOne(ReferralGrant::class, 'proposal_id');
    }
}
