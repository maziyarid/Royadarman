<?php

namespace App\Support;

use DateTimeInterface;

final class WaitClock
{
    public static function minutes(?DateTimeInterface $at): ?int
    {
        if ($at === null) {
            return null;
        }

        return (int) max(0, round(abs($at->getTimestamp() - time()) / 60));
    }

    public static function band(?int $minutes): string
    {
        if ($minutes === null) {
            return 'unknown';
        }
        if ($minutes < 240) {
            return 'ok';
        }
        if ($minutes < 1440) {
            return 'attention';
        }

        return 'overdue';
    }

    public static function remainingMinutes(?DateTimeInterface $expiresAt): ?int
    {
        if ($expiresAt === null) {
            return null;
        }

        return (int) round(($expiresAt->getTimestamp() - time()) / 60);
    }

    public static function expiryBand(?int $remainingMinutes): string
    {
        if ($remainingMinutes === null) {
            return 'unknown';
        }
        if ($remainingMinutes < 0) {
            return 'overdue';
        }
        if ($remainingMinutes < 240) {
            return 'attention';
        }

        return 'ok';
    }

    /** @return array{wait_minutes: ?int, sla_band: string} */
    public static function waiting(?DateTimeInterface $at): array
    {
        $minutes = self::minutes($at);

        return [
            'wait_minutes' => $minutes,
            'sla_band' => self::band($minutes),
        ];
    }
}
