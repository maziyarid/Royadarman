<?php

declare(strict_types=1);

/**
 * Mobile display is not a query correction.
 * No database. No Jalali conversion. The windows below are the G1.2 server bounds.
 */

require_once dirname(__DIR__, 2).'/app/Domain/Scheduling/OperationsCalendarProjection.php';

use App\Domain\Scheduling\OperationsCalendarProjection;

/** @return list<string> */
function operations_projection_failures(): array
{
    $failures = [];
    $check = static function (bool $ok, string $message) use (&$failures): void {
        if (!$ok) {
            $failures[] = $message;
        }
    };
    $throw = static function (callable $fn, string $code) use ($check): void {
        try {
            $fn();
            $check(false, "expected {$code}");
        } catch (InvalidArgumentException $e) {
            $check($e->getMessage() === $code, "expected {$code} got ".$e->getMessage());
        }
    };

    $esfand = ['start_utc' => '2025-02-18 20:30:00', 'end_utc' => '2025-03-20 20:30:00'];
    $farvardin = ['start_utc' => '2025-03-20 20:30:00', 'end_utc' => '2025-04-20 20:30:00'];
    $inside = [
        'id' => 'SYN-TASK-1',
        'kind' => 'task',
        'at_utc' => '2025-03-20 20:29:59',
        'status' => 'open',
        'url' => '/fa/panel/tasks?status=open',
        'title' => 'ignored',
    ];
    $boundary = [
        'id' => 'SYN-TASK-2',
        'kind' => 'task',
        'at_utc' => '2025-03-20 20:30:00',
        'status' => 'open',
        'url' => '/en/panel/cases/SYN-CASE-1',
    ];

    $throw(
        static fn () => OperationsCalendarProjection::project($esfand, [$inside], ['month_length' => 29]),
        'client_must_not_recompute_bounds',
    );
    $throw(
        static fn () => OperationsCalendarProjection::project($esfand, [$inside], ['jmonth' => '1403-12']),
        'client_must_not_recompute_bounds',
    );

    $projected = OperationsCalendarProjection::project($esfand, [$inside, $boundary]);
    $check($projected['client_must_not_recompute_bounds'] === true, 'client flag missing');
    $check($projected['window']['half_open'] === true, 'window is not half-open');
    $check($projected['window']['start_utc'] === '2025-02-18 20:30:00', 'server start was replaced');
    $check($projected['window']['end_utc'] === '2025-03-20 20:30:00', 'server end was replaced');
    $check(count($projected['events']) === 1, 'Esfand projection count '.count($projected['events']));
    $check($projected['events'][0]['id'] === 'SYN-TASK-1', 'the in-window instant was dropped');
    $check($projected['excluded_outside_window'] === 1, 'the next month instant was displayed');
    $check(!array_key_exists('title', $projected['events'][0]), 'a display title was forwarded');

    $next = OperationsCalendarProjection::project($farvardin, [$inside, $boundary]);
    $check(count($next['events']) === 1 && $next['events'][0]['id'] === 'SYN-TASK-2', 'boundary instant was not in the next window exactly once');
    $check($next['media_type'] === OperationsCalendarProjection::MEDIA_TYPE, 'media type drifted');

    $throw(
        static fn () => OperationsCalendarProjection::project($esfand, [array_replace($inside, ['kind' => 'clinic_booking'])]),
        'scheduling_wrong_calendar',
    );
    $throw(
        static fn () => OperationsCalendarProjection::project($esfand, [$inside, $inside]),
        'duplicate_event',
    );
    $throw(
        static fn () => OperationsCalendarProjection::project($esfand, [array_replace($inside, ['url' => '/fa/panel/cases/SYN-CASE-1?token=abc'])]),
        'secret_field',
    );
    $throw(
        static fn () => OperationsCalendarProjection::project($esfand, [array_replace($inside, ['phone' => '09120000000'])]),
        'secret_field',
    );
    $empty = OperationsCalendarProjection::project($esfand, []);
    $check($empty['events'] === [], 'an empty month invented an event');

    return $failures;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? ($argv[0] ?? '')) === __FILE__) {
    $failures = operations_projection_failures();
    if ($failures !== []) {
        fwrite(STDERR, implode("\n", $failures)."\n");
        fwrite(STDOUT, "FAIL operations projection\nORACLE_EXIT 1\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS operations projection\nSYNTHETIC_ONLY no database\nORACLE_EXIT 0\n");
}
