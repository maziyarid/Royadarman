<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tag extends Model
{
    protected $table = 'cms_tags';

    protected $guarded = [];

    public function translations(): HasMany
    {
        return $this->hasMany(TagTranslation::class, 'tag_id');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'cms_post_tag', 'tag_id', 'post_id');
    }
}
