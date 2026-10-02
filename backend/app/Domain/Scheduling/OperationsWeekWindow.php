<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use App\Support\JalaliCalendar;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Saturday-first half-open week for the coordinator operations calendar.
 *
 * The week is seven local midnights in the server zone, returned as UTC.
 * It may cross a Jalali month. Friday is a marker, not a closure.
 * A client does not pass the start, the length, or a closure.
 * This class does not query a database or open a route.
 */
final class OperationsWeekWindow
{
    /**
     * @return array{
     *     start_utc:string,
     *     end_utc:string,
     *     utc_seconds:int,
     *     day_count:int,
     *     days:list<array{0:int,1:int,2:int}>,
     *     half_open:bool,
     *     saturday_first:bool,
     *     friday_is_closure:bool,
     *     client_must_not_recompute_bounds:bool,
     *     time_zone:string
     * }
     */
    public static function containing(int $year, int $month, int $day, string $timezone = 'Asia/Tehran'): array
    {
        if ($year < 1200 || $year > 1600 || ! JalaliCalendar::isValid($year, $month, $day)) {
            throw new InvalidArgumentException('invalid_jdate');
        }
        if ($timezone === '' || str_contains($timezone, '/../')) {
            throw new InvalidArgumentException('invalid_timezone');
        }

        $zone = new DateTimeZone($timezone);
        [$gy, $gm, $gd] = JalaliCalendar::toGregorian($year, $month, $day);
        $utc = new DateTimeZone('UTC');
        $civil = new DateTimeImmutable(sprintf('%04d-%02d-%02d 12:00:00', $gy, $gm, $gd), $utc);
        $sinceSaturday = ((int) $civil->format('w') + 1) % 7;
        $saturday = $civil->modify(sprintf('-%d day', $sinceSaturday));

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $cursor = $saturday->modify(sprintf('+%d day', $i));
            $days[] = JalaliCalendar::fromGregorian(
                (int) $cursor->format('Y'),
                (int) $cursor->format('n'),
                (int) $cursor->format('j'),
            );
        }
        $endCivil = $saturday->modify('+7 day');
        $start = self::midnight($saturday, $zone);
        $end = self::midnight($endCivil, $zone);
        if ($end <= $start || count(array_unique(array_map(
            static fn (array $date): string => sprintf('%04d-%02d-%02d', $date[0], $date[1], $date[2]),
            $days,
        ), SORT_STRING)) !== 7) {
            throw new InvalidArgumentException('grid_window_mismatch');
        }

        $found = false;
        foreach ($days as $date) {
            if ($date === [$year, $month, $day]) {
                $found = true;
                break;
            }
        }
        if (! $found) {
            throw new InvalidArgumentException('grid_window_mismatch');
        }

        return [
            'start_utc' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
            'end_utc' => $end->setTimezone($utc)->format('Y-m-d H:i:s'),
            'utc_seconds' => $end->getTimestamp() - $start->getTimestamp(),
            'day_count' => 7,
            'days' => $days,
            'half_open' => true,
            'saturday_first' => true,
            'friday_is_closure' => false,
            'client_must_not_recompute_bounds' => true,
            'time_zone' => $timezone,
        ];
    }

    private static function midnight(DateTimeImmutable $civilNoonUtc, DateTimeZone $zone): DateTimeImmutable
    {
        $stamp = sprintf('%04d-%02d-%02d 00:00:00', (int) $civilNoonUtc->format('Y'), (int) $civilNoonUtc->format('n'), (int) $civilNoonUtc->format('j'));
        $local = new DateTimeImmutable($stamp, $zone);
        if ($local->format('Y-m-d H:i:s') !== $stamp) {
            throw new InvalidArgumentException('grid_window_mismatch');
        }

        return $local;
    }
}
