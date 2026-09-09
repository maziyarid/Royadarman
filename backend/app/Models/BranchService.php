<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchService extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'min_price' => 'integer',
            'max_price' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    public function clinicBranch(): BelongsTo
    {
        return $this->belongsTo(ClinicBranch::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
