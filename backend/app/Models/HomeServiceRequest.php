<?php

namespace App\Models;

use App\Domain\Cases\Enums\HomeServiceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomeServiceRequest extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => HomeServiceStatus::class,
            'patient_confirmed_at' => 'immutable_datetime',
            'scheduled_for' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'cancel_reason' => 'encrypted',
            'version' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(HomeServiceStatusEvent::class, 'home_service_request_id');
    }
}
