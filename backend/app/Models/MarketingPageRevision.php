<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingPageRevision extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'marketing_page_id', 'version', 'title', 'excerpt', 'body', 'meta_title',
        'meta_description', 'status', 'actor_user_id', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'version' => 'integer'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(MarketingPage::class, 'marketing_page_id');
    }
}
