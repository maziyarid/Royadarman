<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use App\Support\JalaliCalendar;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Server-built half-open month window for the coordinator operations calendar.
 *
 * Asia/Tehran is the display zone unless the server passes its own configured
 * zone. A client does not pass a month length, a replacement end, or a holiday.
 * Friday is not a closure. This class does not query a database or open a route.
 */
final class OperationsMonthWindow
{
    /**
     * @return array{
     *     start_utc:string,
     *     end_utc:string,
     *     start_year:int,
     *     start_month:int,
     *     start_day:int,
     *     end_year:int,
     *     end_month:int,
     *     end_day:int,
     *     day_count:int,
     *     next_year:int,
     *     next_month:int,
     *     half_open:bool,
     *     client_must_not_recompute_bounds:bool,
     *     time_zone:string
     * }
     */
    public static function jalali(int $year, int $month, string $timezone = 'Asia/Tehran'): array
    {
        if ($year < 1200 || $year > 1600 || $month < 1 || $month > 12) {
            throw new InvalidArgumentException('invalid_jmonth');
        }
        if ($timezone === '' || str_contains($timezone, '/../')) {
            throw new InvalidArgumentException('invalid_timezone');
        }

        $zone = new DateTimeZone($timezone);
        [$startYear, $startMonth, $startDay] = JalaliCalendar::toGregorian($year, $month, 1);
        $nextYear = $month === 12 ? $year + 1 : $year;
        $nextMonth = $month === 12 ? 1 : $month + 1;
        [$endYear, $endMonth, $endDay] = JalaliCalendar::toGregorian($nextYear, $nextMonth, 1);
        $start = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $startYear, $startMonth, $startDay), $zone);
        $end = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $endYear, $endMonth, $endDay), $zone);
        if ($end <= $start) {
            throw new InvalidArgumentException('invalid_window');
        }

        $dayCount = JalaliCalendar::monthLength($year, $month);
        $midnights = 0;
        $cursor = $start;
        while ($cursor < $end) {
            $midnights++;
            if ($midnights > 31) {
                break;
            }
            $cursor = $cursor->modify('+1 day');
        }
        if ($midnights !== $dayCount) {
            throw new InvalidArgumentException('grid_window_mismatch');
        }

        $utc = new DateTimeZone('UTC');

        return [
            'start_utc' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
            'end_utc' => $end->setTimezone($utc)->format('Y-m-d H:i:s'),
            'start_year' => $startYear,
            'start_month' => $startMonth,
            'start_day' => $startDay,
            'end_year' => $endYear,
            'end_month' => $endMonth,
            'end_day' => $endDay,
            'day_count' => $dayCount,
            'next_year' => $nextYear,
            'next_month' => $nextMonth,
            'half_open' => true,
            'client_must_not_recompute_bounds' => true,
            'time_zone' => $timezone,
        ];
    }
}
