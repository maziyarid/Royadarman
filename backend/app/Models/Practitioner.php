<?php

namespace App\Models;

use App\Domain\Identity\Enums\CredentialStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Practitioner extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['licence_number', 'licence_hash'];

    protected function casts(): array
    {
        return [
            'credential_status' => CredentialStatus::class,
            'verified_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ClinicMembership::class, 'user_id', 'user_id');
    }

    public function reviewRevisions(): HasMany
    {
        return $this->hasMany(ReviewRevision::class, 'clinician_user_id', 'user_id');
    }

    public function isCurrentlyVerified(): bool
    {
        return $this->credential_status === CredentialStatus::Verified
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
