<?php

namespace App\Domain\Matching\Enums;

enum MatchStrategy: string
{
    case Proximity = 'proximity';
    case Availability = 'availability';
    case Fairness = 'fairness';
    case Hybrid = 'hybrid';
    case Price = 'price';
    case Rating = 'rating';

    public static function default(): self
    {
        return self::Hybrid;
    }

    public static function all(): array
    {
        return [
            self::Proximity->value,
            self::Availability->value,
            self::Fairness->value,
            self::Hybrid->value,
            self::Price->value,
            self::Rating->value,
        ];
    }
}
