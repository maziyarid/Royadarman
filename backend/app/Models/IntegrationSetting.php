<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class IntegrationSetting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by_user_id'];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }
}
