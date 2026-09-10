<?php

namespace App\Models;

use App\Domain\Cases\Enums\CaseStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseStatusEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['case_id', 'actor_user_id', 'from_status', 'to_status', 'reason', 'correlation_id', 'created_at'];

    protected function casts(): array
    {
        return [
            'from_status' => CaseStatus::class,
            'to_status' => CaseStatus::class,
            'reason' => 'encrypted',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }
}
