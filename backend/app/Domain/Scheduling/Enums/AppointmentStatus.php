<?php

namespace App\Domain\Scheduling\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Rescheduled = 'rescheduled';

    public function isActive(): bool
    {
        return $this === self::Pending || $this === self::Confirmed;
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed || $this === self::NoShow;
    }

    public static function all(): array
    {
        return [
            self::Pending->value,
            self::Confirmed->value,
            self::Cancelled->value,
            self::Completed->value,
            self::NoShow->value,
            self::Rescheduled->value,
        ];
    }
}
