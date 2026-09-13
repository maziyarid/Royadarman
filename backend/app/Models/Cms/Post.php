<?php

namespace App\Models\Cms;

use App\Domain\CMS\Enums\PostStatus;
use App\Domain\CMS\Enums\PostType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $table = 'cms_posts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => PostType::class,
            'status' => PostStatus::class,
            'published_at' => 'immutable_datetime',
            'scheduled_at' => 'immutable_datetime',
            'is_featured' => 'boolean',
            'view_count' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PostTranslation::class, 'post_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'cms_post_category', 'post_id', 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'cms_post_tag', 'post_id', 'tag_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class, 'post_id');
    }

    public function seoMetadata(): MorphMany
    {
        return $this->morphMany(SeoMetadata::class, 'entity');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published->value)
            ->where('published_at', '<=', now());
    }
}
