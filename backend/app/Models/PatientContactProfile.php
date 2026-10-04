<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PatientContactProfile extends Model
{
    protected $table = 'patient_contact_profiles';
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['user_id', 'province', 'city', 'neighborhood', 'address', 'contact_email', 'birth_date', 'postal_code', 'preferred_contact_time', 'latitude', 'longitude', 'location_consented_at', 'version'];
    protected $hidden = ['neighborhood', 'address', 'contact_email', 'birth_date', 'postal_code', 'latitude', 'longitude'];

    protected function casts(): array
    {
        return [
            'neighborhood' => 'encrypted',
            'address' => 'encrypted',
            'contact_email' => 'encrypted',
            'birth_date' => 'encrypted',
            'postal_code' => 'encrypted',
            'latitude' => 'encrypted',
            'longitude' => 'encrypted',
            'location_consented_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }
}
