<?php

namespace App\Models;

use App\Domain\Discovery\Enums\SuitabilityStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicServiceCapability extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'suitability_status' => SuitabilityStatus::class,
            'attested_at' => 'immutable_datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function attestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attested_by_user_id');
    }
}
