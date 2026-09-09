<?php

namespace App\Domain\Finance\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function isPaid(): bool
    {
        return $this === self::Paid || $this === self::PartiallyRefunded;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled || $this === self::Refunded;
    }

    public static function all(): array
    {
        return [
            self::Draft->value,
            self::Pending->value,
            self::Paid->value,
            self::Failed->value,
            self::Cancelled->value,
            self::Refunded->value,
            self::PartiallyRefunded->value,
        ];
    }
}
