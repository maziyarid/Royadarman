<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class CaseLocation extends Model
{
    protected $table = 'case_locations';
    protected $primaryKey = 'case_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['case_id', 'province', 'city', 'neighborhood', 'address', 'latitude', 'longitude', 'location_consented_at'];
    protected $hidden = ['neighborhood', 'address', 'latitude', 'longitude'];

    protected function casts(): array
    {
        return [
            'neighborhood' => 'encrypted',
            'address' => 'encrypted',
            'latitude' => 'encrypted',
            'longitude' => 'encrypted',
            'location_consented_at' => 'immutable_datetime',
        ];
    }
}
