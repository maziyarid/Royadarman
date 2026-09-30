<?php
/**
 * Read-only probe of production JalaliCalendar month seams.
 * Expects exactly two mismatches in 1398-1408. No database.
 */
declare(strict_types=1);

$class = getenv('JALALI_CLASS_PATH');
if (!$class || !is_file($class)) {
    fwrite(STDERR, "Set JALALI_CLASS_PATH to a copy of app/Support/JalaliCalendar.php\n");
    exit(2);
}
require $class;

use App\Support\JalaliCalendar;

$tz = new DateTimeZone('Asia/Tehran');
$mismatches = [];
for ($y = 1398; $y <= 1408; $y++) {
    for ($m = 1; $m <= 12; $m++) {
        $len = JalaliCalendar::monthLength($y, $m);
        $ny = $m === 12 ? $y + 1 : $y;
        $nm = $m === 12 ? 1 : $m + 1;
        [$ly, $lm, $ld] = JalaliCalendar::toGregorian($y, $m, $len);
        [$ey, $em, $ed] = JalaliCalendar::toGregorian($ny, $nm, 1);
        $last = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $ly, $lm, $ld), $tz);
        $next = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $ey, $em, $ed), $tz);
        [$by, $bm, $bd] = JalaliCalendar::fromGregorian($ly, $lm, $ld);
        $badRound = ($by !== $y || $bm !== $m || $bd !== $len);
        $gap = $last->modify('+1 day')->format('Y-m-d') !== $next->format('Y-m-d');
        if ($badRound || $gap) {
            $mismatches[] = sprintf('%04d-%02d', $y, $m);
        }
    }
}
sort($mismatches);
$expected = ['1403-12', '1404-12'];
echo ($mismatches === $expected ? 'PASS' : 'FAIL').' month seams '.json_encode($mismatches)."\n";
echo "SYNTHETIC_ONLY no database\n";
exit($mismatches === $expected ? 0 : 1);
