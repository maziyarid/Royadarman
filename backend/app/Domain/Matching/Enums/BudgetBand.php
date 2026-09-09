<?php

namespace App\Domain\Matching\Enums;

enum BudgetBand: string
{
    case Economic = 'economic';
    case Balanced = 'balanced';
    case Flexible = 'flexible';
    case Call = 'call';

    public static function all(): array
    {
        return [
            self::Economic->value,
            self::Balanced->value,
            self::Flexible->value,
            self::Call->value,
        ];
    }
}
