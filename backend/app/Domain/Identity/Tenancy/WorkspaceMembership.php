<?php

namespace App\Domain\Identity\Tenancy;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class WorkspaceMembership extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active_from' => 'immutable_datetime', 'active_until' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime', 'version' => 'integer'];
    }
}
