<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrgencyAssessment extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'assessed_at' => 'datetime',
            'score' => 'integer',
            'criteria' => 'json',
        ];
    }

    public function referralRequest(): BelongsTo
    {
        return $this->belongsTo(ReferralRequest::class);
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_user_id');
    }
}
