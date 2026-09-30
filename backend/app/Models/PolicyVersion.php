<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class PolicyVersion extends Model
{
    use HasUlids;

    /** @var list<string> */
    private const DEMO_VERSION_PREFIXES = ['panel-demo-', 'demo-panel-'];

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

    public function scopeProductionEligible(Builder $query): Builder
    {
        foreach (self::DEMO_VERSION_PREFIXES as $prefix) {
            $query->where('version', 'not like', $prefix.'%');
        }

        return $query;
    }

    public function isSyntheticDemo(): bool
    {
        foreach (self::DEMO_VERSION_PREFIXES as $prefix) {
            if (str_starts_with((string) $this->version, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime'];
    }
}
