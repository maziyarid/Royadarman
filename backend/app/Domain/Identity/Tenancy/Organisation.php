<?php

namespace App\Domain\Identity\Tenancy;

use Illuminate\Database\Eloquent\Model;

final class Organisation extends Model
{
    protected $primaryKey = 'clinic_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
