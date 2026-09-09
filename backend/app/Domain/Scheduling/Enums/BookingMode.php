<?php

namespace App\Domain\Scheduling\Enums;

enum BookingMode: string
{
    case Instant = 'instant';
    case Manual = 'manual';
    case Both = 'both';

    public static function all(): array
    {
        return [
            self::Instant->value,
            self::Manual->value,
            self::Both->value,
        ];
    }
}
