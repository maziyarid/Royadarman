<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostTranslation extends Model
{
    protected $table = 'cms_post_translations';

    protected $guarded = [];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
