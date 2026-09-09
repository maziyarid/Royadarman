<?php

namespace App\Domain\Finance\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function isSuccessful(): bool
    {
        return $this === self::Succeeded;
    }

    public function isCompleted(): bool
    {
        return $this === self::Succeeded || $this === self::Failed || $this === self::Cancelled;
    }

    public static function all(): array
    {
        return [
            self::Pending->value,
            self::Processing->value,
            self::Succeeded->value,
            self::Failed->value,
            self::Cancelled->value,
            self::Refunded->value,
        ];
    }
}
