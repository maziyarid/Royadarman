<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVerification extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['file_path', 'file_hash'];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(Dentist::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
