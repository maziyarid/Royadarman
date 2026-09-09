<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicCredentialing extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requirements' => 'json',
            'submitted_documents' => 'json',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
