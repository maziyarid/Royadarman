<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Media extends Model
{
    protected $table = 'cms_media';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(MediaTranslation::class, 'media_id');
    }
}
