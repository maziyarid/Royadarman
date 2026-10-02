<?php

declare(strict_types=1);

/**
 * The controller and this window are the same builder.
 * No database, holiday list, or clinic table. PHPUnit is optional.
 * `php backend/tests/Support/OperationsMonthWindowOracle.php`
 */

require_once dirname(__DIR__, 2).'/app/Support/JalaliCalendar.php';
require_once dirname(__DIR__, 2).'/app/Domain/Scheduling/OperationsMonthWindow.php';

use App\Domain\Scheduling\OperationsMonthWindow;
use App\Support\JalaliCalendar;

/** @return list<string> */
function operations_month_window_failures(): array
{
    $failures = [];
    $check = static function (bool $ok, string $message) use (&$failures): void {
        if (!$ok) {
            $failures[] = $message;
        }
    };

    $method = new ReflectionMethod(OperationsMonthWindow::class, 'jalali');
    $names = array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $method->getParameters());
    $check($names === ['year', 'month', 'timezone'], 'a client month length is not an input');

    $edges = [
        [1403, 12, '2025-02-18 20:30:00', '2025-03-20 20:30:00', 30],
        [1404, 12, '2026-02-19 20:30:00', '2026-03-20 20:30:00', 29],
        [1404, 1, '2025-03-20 20:30:00', '2025-04-20 20:30:00', 31],
        [1405, 1, '2026-03-20 20:30:00', '2026-04-20 20:30:00', 31],
        [1401, 1, '2022-03-20 20:30:00', '2022-04-20 19:30:00', 31],
        [1401, 6, '2022-08-22 19:30:00', '2022-09-22 20:30:00', 31],
    ];
    foreach ($edges as [$year, $month, $start, $end, $days]) {
        $window = OperationsMonthWindow::jalali($year, $month);
        $check($window['start_utc'] === $start, "{$year}-{$month} start {$window['start_utc']}");
        $check($window['end_utc'] === $end, "{$year}-{$month} end {$window['end_utc']}");
        $check($window['day_count'] === $days, "{$year}-{$month} days {$window['day_count']}");
        $check($window['day_count'] === JalaliCalendar::monthLength($year, $month), "{$year}-{$month} grid");
        $check($window['half_open'] === true && $window['client_must_not_recompute_bounds'] === true, 'bounds stay server-owned');
        $check(!array_key_exists('closed', $window) && !array_key_exists('holiday', $window), 'no invented closure');
    }

    $farvardin = OperationsMonthWindow::jalali(1401, 1);
    $shahrivar = OperationsMonthWindow::jalali(1401, 6);
    $mehr = OperationsMonthWindow::jalali(1401, 7);
    $check($mehr['start_utc'] === $shahrivar['end_utc'], '1401-07 starts where 1401-06 ends');
    $startAt = new DateTimeImmutable($farvardin['start_utc'], new DateTimeZone('UTC'));
    $endAt = new DateTimeImmutable($farvardin['end_utc'], new DateTimeZone('UTC'));
    $check($endAt->getTimestamp() - $startAt->getTimestamp() === 31 * 86400 - 3600, '1401-01 loses the skipped hour');
    $startAt = new DateTimeImmutable($shahrivar['start_utc'], new DateTimeZone('UTC'));
    $endAt = new DateTimeImmutable($shahrivar['end_utc'], new DateTimeZone('UTC'));
    $check($endAt->getTimestamp() - $startAt->getTimestamp() === 31 * 86400 + 3600, '1401-06 gains the repeated hour');

    foreach ([[1199, 12], [1601, 1], [1404, 0], [1404, 13]] as [$year, $month]) {
        $threw = false;
        try {
            OperationsMonthWindow::jalali($year, $month);
        } catch (InvalidArgumentException $exception) {
            $threw = $exception->getMessage() === 'invalid_jmonth';
        }
        $check($threw, "rejected {$year}-{$month}");
    }

    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Web/OperationsCalendarController.php');
    $check(str_contains($controller, 'OperationsMonthWindow::jalali('), 'controller uses the shared window');
    $check(!str_contains($controller, 'toGregorian($jalaliYear, $jalaliMonth, 1)'), 'controller does not keep a second month start');
    $check(str_contains($controller, "'serverWindow'"), 'the response carries the server window');
    $check(substr_count($controller, 'client_must_not_recompute_bounds') === 1, 'the flag is server-set once');

    return $failures;
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    $failures = operations_month_window_failures();
    if ($failures === []) {
        fwrite(STDOUT, "PASS operations month window\n");
        exit(0);
    }
    fwrite(STDERR, implode("\n", $failures)."\n");
    exit(1);
}
