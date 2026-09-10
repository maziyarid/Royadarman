<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['actor_user_id', 'action', 'resource_type', 'resource_id', 'result', 'reason', 'context', 'correlation_id', 'created_at'];

    protected $hidden = ['context'];

    protected function casts(): array
    {
        return [
            'reason' => 'encrypted',
            'context' => 'encrypted:array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
