<?php

namespace App\Domain\Scheduling\Enums;

enum HoldStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Converted = 'converted';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public static function all(): array
    {
        return [
            self::Active->value,
            self::Expired->value,
            self::Converted->value,
            self::Cancelled->value,
        ];
    }
}
