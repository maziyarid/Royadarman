<?php

namespace App\Domain\Finance\Enums;

enum TransactionType: string
{
    case Authorization = 'authorization';
    case Capture = 'capture';
    case Refund = 'refund';
    case Chargeback = 'chargeback';
    case Settlement = 'settlement';
    case Fee = 'fee';
    case Adjustment = 'adjustment';

    public static function all(): array
    {
        return [
            self::Authorization->value,
            self::Capture->value,
            self::Refund->value,
            self::Chargeback->value,
            self::Settlement->value,
            self::Fee->value,
            self::Adjustment->value,
        ];
    }
}
