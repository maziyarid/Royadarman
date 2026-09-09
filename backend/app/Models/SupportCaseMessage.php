<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportCaseMessage extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attachments' => 'json',
            'read_at' => 'datetime',
        ];
    }

    public function supportCase(): BelongsTo
    {
        return $this->belongsTo(SupportCase::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
