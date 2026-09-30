<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CoordinationTask extends Model
{
    use HasUlids;

    protected $fillable = [
        'case_id',
        'assignee_user_id',
        'task_type',
        'status',
        'operational_note',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'immutable_datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }
}
