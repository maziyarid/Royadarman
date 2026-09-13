<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $table = 'cms_redirects';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
            'hit_count' => 'integer',
        ];
    }
}
