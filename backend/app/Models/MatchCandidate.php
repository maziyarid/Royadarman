<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MatchCandidate extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:10,2',
            'travel_time_minutes' => 'integer',
            'score' => 'integer',
            'rank' => 'integer',
            'is_available' => 'boolean',
            'available_at' => 'datetime',
            'expires_at' => 'datetime',
            'score_breakdown' => 'json',
        ];
    }

    public function matchRun(): BelongsTo
    {
        return $this->belongsTo(MatchRun::class);
    }

    public function clinicBranch(): BelongsTo
    {
        return $this->belongsTo(ClinicBranch::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function matchDecision(): HasOne
    {
        return $this->hasOne(MatchDecision::class);
    }
}
