<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $table = 'cms_menus';

    protected $guarded = [];

    public function translations(): HasMany
    {
        return $this->hasMany(MenuTranslation::class, 'menu_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'menu_id');
    }
}
