<?php

namespace App\Models;

use App\Domain\Documents\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalDocument extends Model
{
    use HasUlids;

    protected $table = 'clinical_documents';

    protected $fillable = [
        'case_id',
        'uploaded_by_user_id',
        'original_name',
        'storage_disk',
        'storage_key',
        'detected_mime',
        'byte_size',
        'sha256',
        'status',
        'scan_provider',
        'scan_reference',
        'scan_result',
        'scan_attempted_at',
        'scan_completed_at',
        'scan_error_code',
        'approved_at',
        'retention_until',
        'deleted_at',
    ];

    protected $hidden = ['storage_key', 'scan_reference', 'scan_result'];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'original_name' => 'encrypted',
            'scan_result' => 'encrypted:array',
            'scan_attempted_at' => 'immutable_datetime',
            'scan_completed_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'retention_until' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }
}

