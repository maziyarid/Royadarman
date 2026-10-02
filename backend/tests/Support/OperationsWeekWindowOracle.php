<?php

declare(strict_types=1);

/**
 * Saturday-first operations week. No database, route, or holiday list.
 * PHPUnit is optional.
 * `php backend/tests/Support/OperationsWeekWindowOracle.php`
 */

require_once dirname(__DIR__, 2).'/app/Support/JalaliCalendar.php';
require_once dirname(__DIR__, 2).'/app/Domain/Scheduling/OperationsWeekWindow.php';

use App\Domain\Scheduling\OperationsWeekWindow;
use App\Support\JalaliCalendar;

/** @return list<string> */
function operations_week_window_failures(): array
{
    $failures = [];
    $check = static function (bool $ok, string $message) use (&$failures): void {
        if (!$ok) {
            $failures[] = $message;
        }
    };

    $method = new ReflectionMethod(OperationsWeekWindow::class, 'containing');
    $names = array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $method->getParameters());
    $check($names === ['year', 'month', 'day', 'timezone'], 'a client does not pass the week start');

    // Literals observed from Asia/Tehran midnights, not from this class.
    $edges = [
        [1401, 1, 1, '2022-03-18 20:30:00', '2022-03-25 19:30:00', 601200],
        [1400, 12, 28, '2022-03-18 20:30:00', '2022-03-25 19:30:00', 601200],
        [1401, 1, 5, '2022-03-18 20:30:00', '2022-03-25 19:30:00', 601200],
        [1401, 6, 26, '2022-09-16 19:30:00', '2022-09-23 20:30:00', 608400],
        [1401, 6, 30, '2022-09-16 19:30:00', '2022-09-23 20:30:00', 608400],
        [1403, 12, 30, '2025-03-14 20:30:00', '2025-03-21 20:30:00', 604800],
        [1404, 12, 29, '2026-03-13 20:30:00', '2026-03-20 20:30:00', 604800],
        [1405, 1, 1, '2026-03-20 20:30:00', '2026-03-27 20:30:00', 604800],
    ];
    foreach ($edges as [$year, $month, $day, $start, $end, $seconds]) {
        $week = OperationsWeekWindow::containing($year, $month, $day);
        $check($week['start_utc'] === $start, "{$year}-{$month}-{$day} start {$week['start_utc']}");
        $check($week['end_utc'] === $end, "{$year}-{$month}-{$day} end {$week['end_utc']}");
        $check($week['utc_seconds'] === $seconds, "{$year}-{$month}-{$day} seconds {$week['utc_seconds']}");
        $check($week['day_count'] === 7 && count($week['days']) === 7, 'seven midnights');
        $check($week['half_open'] === true && $week['saturday_first'] === true, 'saturday-first half-open week');
        $check($week['friday_is_closure'] === false, 'Friday is not a closure');
        $check($week['client_must_not_recompute_bounds'] === true, 'bounds stay server-owned');
        $check(!array_key_exists('closed', $week) && !array_key_exists('holiday', $week), 'no invented closure');
        $check(in_array([$year, $month, $day], $week['days'], true), 'the requested date is inside the week');
    }

    $nowruz = OperationsWeekWindow::containing(1401, 1, 1);
    $check($nowruz['days'][0] === [1400, 12, 28], '1401-01-01 week starts on 1400-12-28');
    $check($nowruz['days'][6] === [1401, 1, 5], '1401-01-01 week ends on Friday 1401-01-05');
    $check($nowruz['utc_seconds'] === 7 * 86400 - 3600, 'the spring-forward week loses one hour');
    $next = OperationsWeekWindow::containing(1401, 1, 6);
    $check($next['start_utc'] === $nowruz['end_utc'], 'the next week starts where this one ends');

    $fall = OperationsWeekWindow::containing(1401, 6, 30);
    $check($fall['utc_seconds'] === 7 * 86400 + 3600, 'the repeated-hour week gains one hour');
    $check(in_array([1401, 6, 30], $fall['days'], true), 'both clock readings of 1401-06-30 stay one Jalali day');

    $leap = OperationsWeekWindow::containing(1403, 12, 30);
    $leapHits = 0;
    foreach ($leap['days'] as $date) {
        if ($date === [1403, 12, 30]) {
            $leapHits++;
        }
    }
    $check($leapHits === 1, '1403-12-30 appears once');
    $short = OperationsWeekWindow::containing(1404, 12, 29);
    $invented = false;
    foreach ($short['days'] as $date) {
        if ($date[0] === 1404 && $date[1] === 12 && $date[2] === 30) {
            $invented = true;
        }
    }
    $check($invented === false, '1404-12-29 week must not invent 1404-12-30');
    $check($short['end_utc'] === OperationsWeekWindow::containing(1405, 1, 1)['start_utc'], '1405-01-01 opens the next week');

    foreach ($edges as [$year, $month, $day]) {
        $week = OperationsWeekWindow::containing($year, $month, $day);
        foreach ($week['days'] as $index => $date) {
            $again = OperationsWeekWindow::containing($date[0], $date[1], $date[2]);
            $check($again['start_utc'] === $week['start_utc'] && $again['end_utc'] === $week['end_utc'], 'every day shares the week');
            [$gy, $gm, $gd] = JalaliCalendar::toGregorian($date[0], $date[1], $date[2]);
            $local = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $gy, $gm, $gd), new DateTimeZone('Asia/Tehran'));
            $expect = $index === 0 ? '6' : (string) (($index + 6) % 7);
            $check($local->format('w') === $expect, "weekday {$index} for {$date[0]}-{$date[1]}-{$date[2]}");
        }
    }

    $threw = false;
    try {
        OperationsWeekWindow::containing(1404, 12, 30);
    } catch (InvalidArgumentException $exception) {
        $threw = $exception->getMessage() === 'invalid_jdate';
    }
    $check($threw, '1404-12-30 is not a week input');

    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Domain/Scheduling/OperationsWeekWindow.php');
    $check(!str_contains($source, 'Route::') && !str_contains($source, 'holiday'), 'no route and no holiday list');

    return $failures;
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    $failures = operations_week_window_failures();
    if ($failures === []) {
        fwrite(STDOUT, "PASS operations week window\n");
        exit(0);
    }
    fwrite(STDERR, implode("\n", $failures)."\n");
    exit(1);
}
