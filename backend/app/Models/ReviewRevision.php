<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        ];
    }

    public function clinicalDocument(): BelongsTo
    {
        return $this->belongsTo(ClinicalDocument::class, 'clinical_document_id');
    }
}
