<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceSnapshot extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'final_price' => 'integer',
            'pricing_context' => 'json',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
