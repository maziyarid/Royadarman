<?php

namespace App\Domain\Finance\Enums;

enum LedgerAccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public static function all(): array
    {
        return [
            self::Asset->value,
            self::Liability->value,
            self::Equity->value,
            self::Revenue->value,
            self::Expense->value,
        ];
    }
}
