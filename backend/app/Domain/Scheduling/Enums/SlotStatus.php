<?php

namespace App\Domain\Scheduling\Enums;

enum SlotStatus: string
{
    case Available = 'available';
    case Booked = 'booked';
    case Held = 'held';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public static function all(): array
    {
        return [
            self::Available->value,
            self::Booked->value,
            self::Held->value,
            self::Cancelled->value,
            self::Expired->value,
        ];
    }
}
