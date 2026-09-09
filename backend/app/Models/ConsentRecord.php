<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentRecord extends Model
{
    use HasUlids;

    protected $fillable = [
        'case_id',
        'subject_user_id',
        'purpose',
        'policy_version',
        'accepted',
        'evidence',
        'decided_at',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted' => 'boolean',
            'evidence' => 'encrypted:array',
            'decided_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }
}

