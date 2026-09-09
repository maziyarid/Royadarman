<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credential extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['number'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'verified_at' => 'datetime',
            'number' => 'encrypted',
        ];
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(Dentist::class);
    }
}
