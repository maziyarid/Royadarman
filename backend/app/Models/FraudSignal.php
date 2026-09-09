<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudSignal extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['ip_hash', 'user_agent_hash'];

    protected function casts(): array
    {
        return [
            'context' => 'json',
            'triggered_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
