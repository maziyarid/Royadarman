<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicUser extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(ClinicBranch::class, 'clinic_branch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
