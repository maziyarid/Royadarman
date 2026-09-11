<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingPage extends Model
{
    use HasUlids;

    protected $fillable = [
        'slug', 'locale', 'title', 'excerpt', 'body', 'meta_title', 'meta_description',
        'status', 'version', 'created_by_user_id', 'updated_by_user_id', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(MarketingPageRevision::class);
    }
}
