<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'all_day' => 'boolean',
        ];
    }

    public function clinicBranch(): BelongsTo
    {
        return $this->belongsTo(ClinicBranch::class);
    }
}
