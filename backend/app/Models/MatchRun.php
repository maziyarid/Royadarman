<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchRun extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'candidate_count' => 'integer',
            'result_count' => 'integer',
            'criteria' => 'json',
        ];
    }

    public function referralRequest(): BelongsTo
    {
        return $this->belongsTo(ReferralRequest::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(MatchCandidate::class);
    }
}
