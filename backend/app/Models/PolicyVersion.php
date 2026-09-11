<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class PolicyVersion extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (PolicyVersion $policy): void {
            if ($policy->getRawOriginal('published_at') !== null) {
                throw new LogicException('Published policy versions are immutable; create a new version instead.');
            }
        });

        static::deleting(function (PolicyVersion $policy): void {
            if ($policy->getRawOriginal('published_at') !== null) {
                throw new LogicException('Published policy versions cannot be deleted because consent history references them.');
            }
        });
    }

    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime'];
    }
}
