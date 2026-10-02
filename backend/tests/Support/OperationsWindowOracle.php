<?php

declare(strict_types=1);

/**
 * G1.2 persisted operations-calendar window oracle.
 *
 * Synthetic ids only. No production database, patient data, SMS, or holiday API.
 * SQL predicates are the same comparisons as OperationsCalendarController
 * (due_at / scheduled_for / expires_at half-open, assignee + case coordinator,
 * revoked referrals excluded). The fa month bounds now come from
 * OperationsMonthWindow, which the controller also calls.
 *
 * The window itself is built the same way as the controller: toGregorian of
 * day 1 and of the next month's day 1, Asia/Tehran midnight, then UTC.
 * Grid length must equal that half-open day count. Birashk 2820 length is
 * only a diagnostic of the unpatched defect.
 *
 * PHPUnit is optional. `php backend/tests/Support/OperationsWindowOracle.php`
 * runs the same checks. Pass the throwaway DSN as the first argument.
 */

require_once dirname(__DIR__, 2).'/app/Support/JalaliCalendar.php';
require_once dirname(__DIR__, 2).'/app/Domain/Scheduling/OperationsMonthWindow.php';

use App\Support\JalaliCalendar;

/** @return list<string> */
function operations_window_failures(PDO $pdo): array
{
    $failures = [];
    $check = static function (bool $ok, string $message) use (&$failures): void {
        if (!$ok) {
            $failures[] = $message;
        }
    };

    $check(operations_window_access(true, 'coordinator', false) === true, 'active coordinator is allowed');
    foreach ([
        [false, 'coordinator', false, 'inactive'],
        [true, 'owner', false, 'owner'],
        [true, 'support', false, 'support'],
        [true, 'coordinator', true, 'demo'],
        [null, 'coordinator', false, 'missing user'],
    ] as [$active, $role, $demo, $label]) {
        $check(operations_window_access($active, $role, $demo) === false, "denied {$label}");
    }

    $check(operations_window_month_status('1403-12') === null, '1403-12 is a valid jmonth');
    $check(operations_window_month_status('1404-12') === null, '1404-12 is a valid jmonth');
    $check(operations_window_month_status('13') === 422, 'short jmonth is 422');
    $check(operations_window_month_status('1404-13') === 422, 'month 13 is 422');
    $check(operations_window_month_status('1199-12') === 422, 'year below 1200 is 422');
    $check(operations_window_month_status('1404-12-30') === 422, 'a day is not a jmonth');

    $threw = false;
    try {
        JalaliCalendar::toGregorian(1404, 12, 30);
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    $check($threw, '1404-12-30 must not be stored as a Jalali date');

    $expected = [
        '1403-12' => ['2025-03-20 20:30:00', '2025-03-20 20:30:00'],
        '1404-12' => ['2026-02-19 20:30:00', '2026-03-20 20:30:00'],
        '1405-01' => ['2026-03-20 20:30:00', '2026-04-20 20:30:00'],
    ];
    // The two bounds above are recomputed, not trusted. Assert the published edges.
    [$start1403, $end1403] = operations_window_utc(1403, 12);
    [$start1404, $end1404] = operations_window_utc(1404, 12);
    [$start1405, $end1405] = operations_window_utc(1405, 1);
    $check($start1403 === '2025-02-18 20:30:00', "1403-12 start {$start1403}");
    $check($end1403 === '2025-03-20 20:30:00', "1403-12 end {$end1403}");
    $check($start1404 === '2026-02-19 20:30:00', "1404-12 start {$start1404}");
    $check($end1404 === '2026-03-20 20:30:00', "1404-12 end {$end1404}");
    $check($start1405 === '2026-03-20 20:30:00', "1405-01 start {$start1405}");
    $check($end1405 === '2026-04-20 20:30:00', "1405-01 end {$end1405}");
    [$start140401, $end140401] = operations_window_utc(1404, 1);
    $check($start140401 === '2025-03-20 20:30:00', "1404-01 start {$start140401}");
    $check($end140401 === '2025-04-20 20:30:00', "1404-01 end {$end140401}");
    unset($expected);

    $check(JalaliCalendar::monthLength(1403, 12) === 30, 'patched 1403-12 length');
    $check(JalaliCalendar::monthLength(1404, 12) === 29, 'patched 1404-12 length');
    $check(operations_window_day_count(1403, 12) === 30, '1403-12 window has 30 midnights');
    $check(operations_window_day_count(1404, 12) === 29, '1404-12 window has 29 midnights');
    $check(operations_window_day_count(1403, 12) === JalaliCalendar::monthLength(1403, 12), '1403 grid matches window');
    $check(operations_window_day_count(1404, 12) === JalaliCalendar::monthLength(1404, 12), '1404 grid matches window');
    $check(operations_birashk_esfand(1403) === 29, 'diagnostic 2820 still says 1403 has 29');
    $check(operations_birashk_esfand(1404) === 30, 'diagnostic 2820 still says 1404 has 30');

    // Host zoneinfo Asia/Tehran, not a holiday list: spring gap 2022-03-21 20:30Z,
    // autumn repeat 2022-09-21 19:30Z, and no later transition through 2026.
    [$start140101, $end140101] = operations_window_utc(1401, 1);
    [$start140106, $end140106] = operations_window_utc(1401, 6);
    [$start140107] = operations_window_utc(1401, 7);
    $check($start140101 === '2022-03-20 20:30:00', "1401-01 start {$start140101}");
    $check($end140101 === '2022-04-20 19:30:00', "1401-01 end {$end140101}");
    $check($start140106 === '2022-08-22 19:30:00', "1401-06 start {$start140106}");
    $check($end140106 === '2022-09-22 20:30:00', "1401-06 end {$end140106}");
    $check($start140107 === $end140106, '1401-07 starts where 1401-06 ends');
    $check(operations_window_day_count(1401, 1) === 31, '1401-01 has 31 Tehran midnights');
    $check(operations_window_day_count(1401, 6) === 31, '1401-06 has 31 Tehran midnights');
    $check(operations_window_utc_seconds(1401, 1) === 31 * 86400 - 3600, '1401-01 loses the skipped hour');
    $check(operations_window_utc_seconds(1401, 6) === 31 * 86400 + 3600, '1401-06 gains the repeated hour');
    $check(operations_window_day_count(1401, 1) === JalaliCalendar::monthLength(1401, 1), '1401-01 grid matches window');
    $check(operations_window_day_count(1401, 6) === JalaliCalendar::monthLength(1401, 6), '1401-06 grid matches window');
    $gap = new DateTimeImmutable('2022-03-22 00:30:00', new DateTimeZone('Asia/Tehran'));
    $check($gap->format('H:i:s') !== '00:30:00', 'the skipped local hour is not a stored instant');

    operations_window_reset($pdo);

    // utc => [1403-12, 1404-12, 1405-01] membership. 'once' means in that window only.
    $rows = [
        'a' => ['2025-03-19 20:29:59', [1403, 12]],
        'b' => ['2025-03-19 20:30:00', [1403, 12]],
        'c' => ['2025-03-20 20:29:59', [1403, 12]],
        'd' => ['2025-03-20 20:30:00', [1404, 1]],
        'e' => ['2026-03-19 20:30:00', [1404, 12]],
        'f' => ['2026-03-20 20:29:59', [1404, 12]],
        'g' => ['2026-03-20 20:30:00', [1405, 1]],
    ];

    $id = 1;
    $insertTask = $pdo->prepare('INSERT INTO coordination_tasks (id, assignee_user_id, case_id, due_at, status, task_type) VALUES (?, ?, ?, ?, ?, ?)');
    $insertHome = $pdo->prepare('INSERT INTO home_service_requests (id, case_id, scheduled_for, status) VALUES (?, ?, ?, ?)');
    $insertGrant = $pdo->prepare('INSERT INTO referral_grants (id, case_id, expires_at, revoked_at) VALUES (?, ?, ?, NULL)');

    foreach ($rows as $label => [$utc, $jalali]) {
        $insertTask->execute([$id, 101, 1, $utc, 'open', 'follow_up']);
        $insertHome->execute([$id, 1, $utc, 'scheduled']);
        $insertGrant->execute([$id, 1, $utc]);
        $id++;
        unset($label, $jalali);
    }

    // Same instant as row b, other coordinator. Must stay out of 101's month.
    $insertTask->execute([90, 202, 2, '2025-03-19 20:30:00', 'open', 'follow_up']);
    // Assignee is 101 but the case coordinator is 202.
    $insertTask->execute([91, 101, 2, '2025-03-19 20:30:00', 'open', 'follow_up']);
    // Revoked referral at an in-window instant.
    $pdo->prepare('INSERT INTO referral_grants (id, case_id, expires_at, revoked_at) VALUES (92, 1, ?, ?)')
        ->execute(['2025-03-19 20:30:00', '2025-03-18 00:00:00']);
    // Null schedule is not an event.
    $insertHome->execute([93, 1, null, 'unscheduled']);

    $windows = [
        '1403-12' => [1403, 12, ['a', 'b', 'c']],
        '1404-12' => [1404, 12, ['e', 'f']],
        '1405-01' => [1405, 1, ['g']],
        '1404-01' => [1404, 1, ['d']],
    ];
    $labels = array_keys($rows);

    foreach ($windows as $name => [$jy, $jm, $want]) {
        [$start, $end] = operations_window_utc($jy, $jm);
        foreach (['task' => 'due_at', 'home' => 'scheduled_for', 'referral' => 'expires_at'] as $kind => $column) {
            $got = operations_window_query($pdo, $kind, 101, $start, $end);
            $gotLabels = [];
            foreach ($got as $rowId) {
                if ($rowId >= 1 && $rowId <= count($labels)) {
                    $gotLabels[] = $labels[$rowId - 1];
                } else {
                    $gotLabels[] = 'id:'.$rowId;
                }
            }
            sort($gotLabels);
            $expect = $want;
            sort($expect);
            $check($gotLabels === $expect, "{$name} {$kind} got ".json_encode($gotLabels).' want '.json_encode($expect)." window [{$start}, {$end})");
            $check(count($got) === count(array_unique($got)), "{$name} {$kind} duplicated a row");
        }

        $cells = [];
        $length = JalaliCalendar::monthLength($jy, $jm);
        for ($day = 1; $day <= $length; $day++) {
            $cells[$day] = JalaliCalendar::toGregorian($jy, $jm, $day);
        }
        foreach ($want as $label) {
            $utc = $rows[$label][0];
            $local = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Tehran'));
            $jalali = JalaliCalendar::fromGregorian((int) $local->format('Y'), (int) $local->format('n'), (int) $local->format('j'));
            $matches = [];
            foreach ($cells as $day => $gregorian) {
                if ($jalali === [$jy, $jm, $day] && [$local->format('Y'), (int) $local->format('n'), (int) $local->format('j')] === [(string) $gregorian[0], $gregorian[1], $gregorian[2]]) {
                    $matches[] = $day;
                }
            }
            $check(count($matches) === 1, "{$name} {$label} landed in ".json_encode($matches).' cells');
        }
    }

    // Unpatched grid omits the real leap day that the query already includes.
    $leapCells = 0;
    for ($day = 1; $day <= operations_birashk_esfand(1403); $day++) {
        if (JalaliCalendar::fromGregorian(2025, 3, 20) === [1403, 12, $day]) {
            $leapCells++;
        }
    }
    $check($leapCells === 0, '2820 grid unexpectedly contains 1403-12-30');
    $patchedCells = 0;
    for ($day = 1; $day <= JalaliCalendar::monthLength(1403, 12); $day++) {
        if (JalaliCalendar::toGregorian(1403, 12, $day) === [2025, 3, 20]) {
            $patchedCells++;
        }
    }
    $check($patchedCells === 1, 'patched grid must contain 2025-03-20 once');

    $dstRows = [
        10 => ['2022-03-21 20:29:59', 1401, 1],
        11 => ['2022-03-21 20:30:00', 1401, 1],
        12 => ['2022-04-20 19:29:59', 1401, 1],
        13 => ['2022-04-20 19:30:00', 1401, 2],
        14 => ['2022-09-21 18:30:00', 1401, 6],
        15 => ['2022-09-21 19:30:00', 1401, 6],
        16 => ['2022-09-22 20:29:59', 1401, 6],
        17 => ['2022-09-22 20:30:00', 1401, 7],
    ];
    foreach ($dstRows as $rowId => [$utc]) {
        $insertTask->execute([$rowId, 101, 1, $utc, 'open', 'follow_up']);
        $insertHome->execute([$rowId, 1, $utc, 'scheduled']);
        $insertGrant->execute([$rowId, 1, $utc]);
    }
    foreach ([[1401, 1, [10, 11, 12]], [1401, 2, [13]], [1401, 6, [14, 15, 16]], [1401, 7, [17]]] as [$jy, $jm, $want]) {
        [$start, $end] = operations_window_utc($jy, $jm);
        foreach (['task', 'home', 'referral'] as $kind) {
            $got = array_values(array_filter(
                operations_window_query($pdo, $kind, 101, $start, $end),
                static fn (int $id): bool => $id >= 10 && $id <= 17,
            ));
            $check($got === $want, "1401-{$jm} {$kind} dst got ".json_encode($got).' want '.json_encode($want));
        }
    }
    $fallDays = [];
    foreach (['2022-09-21 18:30:00', '2022-09-21 19:30:00'] as $utc) {
        $local = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Tehran'));
        $fallDays[] = JalaliCalendar::fromGregorian((int) $local->format('Y'), (int) $local->format('n'), (int) $local->format('j'));
    }
    $check($fallDays[0] === [1401, 6, 30] && $fallDays[1] === [1401, 6, 30], 'both repeated-hour instants are 1401-06-30');

    $overlap = false;
    try {
        JalaliCalendar::toGregorian(1404, 12, 30);
        $overlap = true;
    } catch (InvalidArgumentException) {
        $overlap = false;
    }
    $check($overlap === false, 'patched calendar must not build a 1404-12-30 cell on 2026-03-21');

    return $failures;
}

function operations_window_access(?bool $active, ?string $role, bool $demo): bool
{
    return $active === true && $role === 'coordinator' && $demo === false;
}

function operations_window_month_status(string $monthInput): ?int
{
    if (!preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
        return 422;
    }
    [$year, $month] = array_map('intval', explode('-', $monthInput));
    if ($year < 1200 || $year > 1600 || $month < 1 || $month > 12) {
        return 422;
    }

    return null;
}

/** @return array{0:string,1:string} UTC 'Y-m-d H:i:s' start inclusive, end exclusive */
function operations_window_utc(int $jy, int $jm): array
{
    $window = \App\Domain\Scheduling\OperationsMonthWindow::jalali($jy, $jm);

    return [$window['start_utc'], $window['end_utc']];
}

function operations_window_day_count(int $jy, int $jm): int
{
    [$start, $end] = operations_window_utc($jy, $jm);
    $tehran = new DateTimeZone('Asia/Tehran');
    $cursor = (new DateTimeImmutable($start, new DateTimeZone('UTC')))->setTimezone($tehran);
    $limit = (new DateTimeImmutable($end, new DateTimeZone('UTC')))->setTimezone($tehran);
    $days = 0;
    while ($cursor < $limit) {
        $days++;
        $cursor = $cursor->modify('+1 day');
    }

    return $days;
}

function operations_window_utc_seconds(int $jy, int $jm): int
{
    [$start, $end] = operations_window_utc($jy, $jm);
    $a = new DateTimeImmutable($start, new DateTimeZone('UTC'));
    $b = new DateTimeImmutable($end, new DateTimeZone('UTC'));

    return $b->getTimestamp() - $a->getTimestamp();
}

function operations_birashk_esfand(int $jy): int
{
    $cycleYear = (($jy - ($jy > 0 ? 474 : 473)) % 2820 + 2820) % 2820 + 474 + 38;

    return (($cycleYear * 682) % 2816) < 682 ? 30 : 29;
}

function operations_window_reset(PDO $pdo): void
{
    $pdo->exec('DROP TABLE IF EXISTS coordination_tasks');
    $pdo->exec('DROP TABLE IF EXISTS home_service_requests');
    $pdo->exec('DROP TABLE IF EXISTS referral_grants');
    $pdo->exec('DROP TABLE IF EXISTS cases');
    $pdo->exec('CREATE TABLE cases (id INT PRIMARY KEY, current_coordinator_id INT NOT NULL, public_reference VARCHAR(32) NOT NULL)');
    $pdo->exec('CREATE TABLE coordination_tasks (id INT PRIMARY KEY, assignee_user_id INT NOT NULL, case_id INT NOT NULL, due_at TIMESTAMP NULL, status VARCHAR(32) NOT NULL, task_type VARCHAR(32) NOT NULL)');
    $pdo->exec('CREATE TABLE home_service_requests (id INT PRIMARY KEY, case_id INT NOT NULL, scheduled_for TIMESTAMP NULL, status VARCHAR(32) NOT NULL)');
    $pdo->exec("CREATE TABLE referral_grants (id INT PRIMARY KEY, case_id INT NOT NULL, expires_at TIMESTAMP NULL, revoked_at TIMESTAMP NULL)");
    $pdo->exec("INSERT INTO cases (id, current_coordinator_id, public_reference) VALUES (1, 101, 'SYN-A'), (2, 202, 'SYN-B')");
}

/** @return list<int> */
function operations_window_query(PDO $pdo, string $kind, int $coordinatorId, string $startUtc, string $endUtc): array
{
    if ($kind === 'task') {
        $sql = 'SELECT t.id FROM coordination_tasks t INNER JOIN cases c ON c.id = t.case_id WHERE t.assignee_user_id = ? AND c.current_coordinator_id = ? AND t.due_at >= ? AND t.due_at < ? ORDER BY t.id';
    } elseif ($kind === 'home') {
        $sql = 'SELECT h.id FROM home_service_requests h INNER JOIN cases c ON c.id = h.case_id WHERE c.current_coordinator_id = ? AND h.scheduled_for IS NOT NULL AND h.scheduled_for >= ? AND h.scheduled_for < ? ORDER BY h.id';
    } elseif ($kind === 'referral') {
        $sql = 'SELECT g.id FROM referral_grants g INNER JOIN cases c ON c.id = g.case_id WHERE c.current_coordinator_id = ? AND g.revoked_at IS NULL AND g.expires_at IS NOT NULL AND g.expires_at >= ? AND g.expires_at < ? ORDER BY g.id';
    } else {
        throw new InvalidArgumentException('Unknown kind');
    }

    $statement = $pdo->prepare($sql);
    if ($kind === 'task') {
        $statement->execute([$coordinatorId, $coordinatorId, $startUtc, $endUtc]);
    } else {
        $statement->execute([$coordinatorId, $startUtc, $endUtc]);
    }

    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? ($argv[0] ?? '')) === __FILE__) {
    $dsn = $argv[1] ?? '';
    if (!str_contains($dsn, 'g12_synthetic')) {
        fwrite(STDERR, "REFUSED dsn must name g12_synthetic\n");
        exit(2);
    }
    if (str_contains($dsn, '3306')) {
        fwrite(STDERR, "REFUSED port 3306\n");
        exit(2);
    }
    $pdo = new PDO($dsn, $argv[2] ?? 'root', $argv[3] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    $failures = operations_window_failures($pdo);
    if ($failures !== []) {
        fwrite(STDERR, implode("\n", $failures)."\n");
        fwrite(STDOUT, "FAIL operations window\nORACLE_EXIT 1\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS operations window\nSYNTHETIC_ONLY throwaway database\nORACLE_EXIT 0\n");
}
