<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchDecision extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function referralRequest(): BelongsTo
    {
        return $this->belongsTo(ReferralRequest::class);
    }

    public function matchCandidate(): BelongsTo
    {
        return $this->belongsTo(MatchCandidate::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
