<?php

namespace App\Models;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\ServiceType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientCase extends Model
{
    use HasUlids;

    protected $table = 'patient_cases';

    protected $fillable = [
        'public_reference',
        'patient_user_id',
        'service_type',
        'status',
        'priority',
        'patient_name',
        'patient_mobile',
        'patient_mobile_hash',
        'tehran_area',
        'preferred_contact_time',
        'contact_reason',
        'budget_band',
        'current_coordinator_id',
        'submitted_at',
        'closed_at',
        'version',
        'source_language',
        'currency',
        'budget_input_unit',
    ];

    protected $hidden = [
        'patient_mobile',
        'patient_mobile_hash',
        'patient_name',
        'contact_reason',
    ];

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'status' => CaseStatus::class,
            'patient_name' => 'encrypted',
            'patient_mobile' => 'encrypted',
            'contact_reason' => 'encrypted',
            'submitted_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_coordinator_id');
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(CaseStatusEvent::class, 'case_id');
    }

    public function consentEvents(): HasMany
    {
        return $this->hasMany(ConsentEvent::class, 'case_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClinicalDocument::class, 'case_id');
    }
}
