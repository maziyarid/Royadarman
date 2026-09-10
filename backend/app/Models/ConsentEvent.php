<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'consent_events';

    protected $fillable = [
        'subject_user_id',
        'case_id',
        'policy_version_id',
        'purpose',
        'decision',
        'locale',
        'channel',
        'ip_hash',
        'user_agent_hash',
        'created_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class, 'policy_version_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }
}
