<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntakeAnswer extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'answer_value' => 'json',
            'is_required' => 'boolean',
            'is_red_flag' => 'boolean',
        ];
    }

    public function referralRequest(): BelongsTo
    {
        return $this->belongsTo(ReferralRequest::class);
    }
}
