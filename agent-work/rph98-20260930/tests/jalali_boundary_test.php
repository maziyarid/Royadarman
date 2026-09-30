<?php
/**
 * Synthetic Jalali boundary checks against a verbatim copy of production
 * App\Support\JalaliCalendar. No database. No production events.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$class = getenv('JALALI_CLASS_PATH') ?: ($root.'/vendor-copy/JalaliCalendar.php');
if (!is_file($class)) {
    fwrite(STDERR, "Missing JalaliCalendar copy at {$class}\n");
    exit(2);
}
require $class;

use App\Support\JalaliCalendar;

$failures = 0;
$defects = [];
$check = function (bool $ok, string $message) use (&$failures): void {
    if ($ok) {
        echo "PASS {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL {$message}\n";
};

$check(JalaliCalendar::toPersianDigits('1405-01-01 09:30') === '۱۴۰۵-۰۱-۰۱ ۰۹:۳۰', 'persian digits preserve separators');

$roundtrip = function (int $jy, int $jm) use ($check): void {
    $length = JalaliCalendar::monthLength($jy, $jm);
    $check($length >= 29 && $length <= 31, "month length {$jy}-{$jm} = {$length}");
    $check(!JalaliCalendar::isValid($jy, $jm, $length + 1), "day after {$jy}-{$jm}-{$length} is invalid");
    for ($jd = 1; $jd <= $length; $jd++) {
        [$gy, $gm, $gd] = JalaliCalendar::toGregorian($jy, $jm, $jd);
        [$backY, $backM, $backD] = JalaliCalendar::fromGregorian($gy, $gm, $gd);
        if ($backY !== $jy || $backM !== $jm || $backD !== $jd) {
            echo "DEFECT roundtrip {$jy}-{$jm}-{$jd} -> {$gy}-{$gm}-{$gd} -> {$backY}-{$backM}-{$backD}\n";
            $GLOBALS['defects'][] = sprintf('%04d-%02d-%02d', $jy, $jm, $jd);
            return;
        }
    }
    $check(true, "roundtrip all days {$jy}-".sprintf('%02d', $jm)." ({$length} days)");
};

foreach ([1399, 1403, 1404, 1405] as $year) {
    $roundtrip($year, 12);
    $roundtrip($year, 1);
}

[$gy, $gm, $gd] = JalaliCalendar::toGregorian(1405, 1, 1);
$check([$gy, $gm, $gd] === [2026, 3, 21], "1405-01-01 is 2026-03-21, got {$gy}-{$gm}-{$gd}");

$dow = function (int $gy, int $gm, int $gd): int {
    $dt = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $gy, $gm, $gd), new DateTimeZone('Asia/Tehran'));
    return (int) $dt->format('w');
};

$saturdayFirst = function (int $phpW): int {
    return ($phpW + 1) % 7;
};

$leadingFor = function (int $jy, int $jm) use ($dow, $saturdayFirst): int {
    [$gy, $gm, $gd] = JalaliCalendar::toGregorian($jy, $jm, 1);
    return $saturdayFirst($dow($gy, $gm, $gd));
};

$check($leadingFor(1405, 1) === 0, 'Farvardin 1405 starts on Saturday so leading blanks are 0');

$fridayStart = null;
$saturdayStart = null;
for ($year = 1400; $year <= 1410 && ($fridayStart === null || $saturdayStart === null); $year++) {
    for ($month = 1; $month <= 12; $month++) {
        $leading = $leadingFor($year, $month);
        if ($leading === 0 && $saturdayStart === null) {
            $saturdayStart = [$year, $month, $leading];
        }
        if ($leading === 6 && $fridayStart === null) {
            $fridayStart = [$year, $month, $leading];
        }
    }
}
$check($saturdayStart !== null, 'found a Saturday-start month '.json_encode($saturdayStart));
$check($fridayStart !== null, 'found a Friday-start month with 6 leading blanks '.json_encode($fridayStart));

$tehran = new DateTimeZone('Asia/Tehran');
$utc = new DateTimeZone('UTC');
$boundary = function (int $jy, int $jm) use ($tehran, $utc, $check): array {
    $nextY = $jm === 12 ? $jy + 1 : $jy;
    $nextM = $jm === 12 ? 1 : $jm + 1;
    [$sy, $sm, $sd] = JalaliCalendar::toGregorian($jy, $jm, 1);
    [$ey, $em, $ed] = JalaliCalendar::toGregorian($nextY, $nextM, 1);
    $start = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $sy, $sm, $sd), $tehran);
    $end = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $ey, $em, $ed), $tehran);
    $check($end > $start, "exclusive end after start for {$jy}-{$jm}");
    $startUtc = $start->setTimezone($utc);
    $endUtc = $end->setTimezone($utc);
    $check($startUtc->format('P') === '+00:00', 'start converted to UTC');
    $inside = $startUtc;
    $before = $startUtc->modify('-1 second');
    $after = $endUtc;
    $check($inside >= $startUtc && $inside < $endUtc, "start instant included {$jy}-{$jm}");
    $check($before < $startUtc, "one second before start excluded {$jy}-{$jm}");
    $check(!($after >= $startUtc && $after < $endUtc), "exclusive end excluded {$jy}-{$jm}");
    return [
        'jalali' => sprintf('%04d-%02d', $jy, $jm),
        'start_tehran' => $start->format('c'),
        'end_exclusive_tehran' => $end->format('c'),
        'start_utc' => $startUtc->format('c'),
        'end_exclusive_utc' => $endUtc->format('c'),
        'leading' => ($start->format('w') + 1) % 7,
        'days' => JalaliCalendar::monthLength($jy, $jm),
    ];
};

$samples = [
    $boundary(1404, 12),
    $boundary(1405, 1),
    $boundary(1399, 12),
    $boundary(1403, 12),
];

if ($fridayStart) {
    $samples[] = $boundary($fridayStart[0], $fridayStart[1]);
}

$threw = false;
try {
    JalaliCalendar::toGregorian(1404, 12, JalaliCalendar::monthLength(1404, 12) + 1);
} catch (InvalidArgumentException) {
    $threw = true;
}
$check($threw, 'invalid Esfand day throws');

echo "SYNTHETIC_ONLY no database and no production events\n";
sort($defects);
$expectedDefects = ['1404-12-30'];
$check($defects === $expectedDefects, 'only known monthLength/conversion mismatch remains: '.json_encode($defects));
echo "DEFECT_JALALI_1404_12_30_OVERLAPS_1405_01_01\n";
echo "DEFECT_JALALI_1403_12_GAP_2025_03_20 verified by tests/jalali_gap_probe.php\n";
echo 'ESFAND_1404_LENGTH '.JalaliCalendar::monthLength(1404, 12)."\n";
echo 'ESFAND_1403_LENGTH '.JalaliCalendar::monthLength(1403, 12)."\n";
echo 'ESFAND_1399_LENGTH '.JalaliCalendar::monthLength(1399, 12)."\n";
echo 'SAMPLES '.json_encode($samples, JSON_UNESCAPED_SLASHES)."\n";

exit($failures === 0 ? 0 : 1);
