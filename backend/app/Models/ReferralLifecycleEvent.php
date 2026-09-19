<?php

namespace App\Models;

use App\Domain\Coordination\Enums\ReferralLifecycleEventType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class ReferralLifecycleEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'proposal_id',
        'case_id',
        'clinic_id',
        'event_type',
        'actor_user_id',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => ReferralLifecycleEventType::class,
            'reason' => 'encrypted',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('referral lifecycle events are append-only');
        });
        static::deleting(function (): never {
            throw new RuntimeException('referral lifecycle events are append-only');
        });
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ReferralProposal::class, 'proposal_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinic_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
