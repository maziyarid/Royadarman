<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatingHour extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_closed' => 'boolean',
            'is_24h' => 'boolean',
        ];
    }

    public function clinicBranch(): BelongsTo
    {
        return $this->belongsTo(ClinicBranch::class);
    }
}
