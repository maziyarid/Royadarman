<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewRevision extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'image_adequacy' => 'encrypted',
            'observations' => 'encrypted',
            'limitations' => 'encrypted',
            'options' => 'encrypted',
            'recommended_next_step' => 'encrypted',
            'signed_at' => 'immutable_datetime',
            'revision_number' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function clinicalDocument(): BelongsTo
    {
        return $this->belongsTo(ClinicalDocument::class, 'clinical_document_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clinician_user_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function supersededBy(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_id');
    }

    public function publicationEvents(): HasMany
    {
        return $this->hasMany(PublicationEvent::class, 'review_revision_id');
    }

    public function isPublished(): bool
    {
        if ($this->relationLoaded('publicationEvents')) {
            return $this->publicationEvents->contains('event', 'published');
        }

        return $this->publicationEvents()
            ->where('event', 'published')
            ->exists();
    }
}
