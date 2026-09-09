<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaTranslation extends Model
{
    protected $table = 'cms_media_translations';

    protected $guarded = [];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
