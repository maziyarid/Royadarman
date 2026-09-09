<?php

namespace App\Models;

use App\Domain\Support\Enums\ConversationStatus;
use App\Domain\Support\Enums\SupportCategory;
use App\Domain\Support\Enums\SupportPriority;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'category' => SupportCategory::class,
            'status' => ConversationStatus::class,
            'priority' => SupportPriority::class,
            'opened_at' => 'immutable_datetime',
            'first_response_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'conversation_id');
    }

    public function visibleMessages(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(SupportStatusEvent::class, 'conversation_id');
    }
}
