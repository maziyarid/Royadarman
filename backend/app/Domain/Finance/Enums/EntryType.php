<?php

namespace App\Domain\Finance\Enums;

enum EntryType: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public static function all(): array
    {
        return [
            self::Debit->value,
            self::Credit->value,
        ];
    }
}
