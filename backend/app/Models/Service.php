<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branchServices(): HasMany
    {
        return $this->hasMany(BranchService::class);
    }

    public function capacityWindows(): HasMany
    {
        return $this->hasMany(CapacityWindow::class);
    }

    public function appointmentSlots(): HasMany
    {
        return $this->hasMany(AppointmentSlot::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function priceSnapshots(): HasMany
    {
        return $this->hasMany(PriceSnapshot::class);
    }
}
