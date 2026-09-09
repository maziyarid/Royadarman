<?php

namespace App\Domain\Matching\Enums;

enum UrgencyLevel: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Emergency = 'emergency';

    public function score(): int
    {
        return match($this) {
            self::Emergency => 100,
            self::High => 75,
            self::Normal => 50,
            self::Low => 25,
        };
    }

    public function isCritical(): bool
    {
        return $this === self::Emergency || $this === self::High;
    }

    public static function all(): array
    {
        return [
            self::Low->value,
            self::Normal->value,
            self::High->value,
            self::Emergency->value,
        ];
    }
}
