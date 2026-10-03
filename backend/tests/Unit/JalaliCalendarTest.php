<?php

namespace Tests\Unit;

use App\Support\JalaliCalendar;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Jalali calendar seam regression tests (G1.1, RPH-94/RPH-65).
 *
 * Fixtures are independently established Nowruz dates and leap-year seams,
 * not values copied from the implementation:
 * - Nowruz 1403 = 2024-03-20, Nowruz 1404 = 2025-03-21 (astronomical year
 *   boundary after noon Tehran on 2025-03-20), so 1403 is leap and 1404 is not.
 * - Derived from that: Nowruz 1405 = 2026-03-21, Nowruz 1407 = 2028-03-20,
 *   Nowruz 1408 = 2029-03-20 (1408 is the next leap year), 1399 is leap.
 */
final class JalaliCalendarTest extends TestCase
{
    /** @var array<int, array{0:int,1:int,2:int,3:int,4:int,5:int}> */
    private const NOWRUZ_AND_SEAM_FIXTURES = [
        [1403, 1, 1, 2024, 3, 20],
        [1403, 12, 29, 2025, 3, 19],
        [1403, 12, 30, 2025, 3, 20],
        [1404, 1, 1, 2025, 3, 21],
        [1404, 12, 29, 2026, 3, 20],
        [1405, 1, 1, 2026, 3, 21],
        [1407, 1, 1, 2028, 3, 20],
        [1408, 1, 1, 2029, 3, 20],
        [1399, 12, 30, 2021, 3, 20],
        [1400, 1, 1, 2021, 3, 21],
    ];

    public function test_seam_fixtures_convert_to_known_gregorian_dates(): void
    {
        foreach (self::NOWRUZ_AND_SEAM_FIXTURES as [$jy, $jm, $jd, $gy, $gm, $gd]) {
            $this->assertSame(
                [$gy, $gm, $gd],
                JalaliCalendar::toGregorian($jy, $jm, $jd),
                "toGregorian({$jy}, {$jm}, {$jd}) must match the independently established date."
            );
        }
    }

    public function test_gregorian_to_jalali_seam_dates(): void
    {
        foreach (self::NOWRUZ_AND_SEAM_FIXTURES as [$jy, $jm, $jd, $gy, $gm, $gd]) {
            $this->assertSame(
                [$jy, $jm, $jd],
                JalaliCalendar::fromGregorian($gy, $gm, $gd),
                "fromGregorian({$gy}, {$gm}, {$gd}) must match the independently established date."
            );
        }
    }

    public function test_1403_is_leap_and_1404_is_not(): void
    {
        $this->assertTrue(JalaliCalendar::isLeap(1403), '1403 is a leap year (Esfand 30 exists).');
        $this->assertSame(30, JalaliCalendar::monthLength(1403, 12));
        $this->assertTrue(JalaliCalendar::isValid(1403, 12, 30));
        $this->assertFalse(JalaliCalendar::isLeap(1404), '1404 is not a leap year.');
        $this->assertSame(29, JalaliCalendar::monthLength(1404, 12));
        $this->assertFalse(JalaliCalendar::isValid(1404, 12, 30), 'The invalid 1404-12-30 seam must stay rejected.');
    }

    public function test_invalid_1404_12_30_is_rejected_by_conversion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JalaliCalendar::toGregorian(1404, 12, 30);
    }

    public function test_leap_year_set_matches_the_conversion_cycle(): void
    {
        $expectedLeapYears = [1391, 1395, 1399, 1403, 1408, 1412, 1416, 1420, 1424];
        foreach (range(1390, 1425) as $jy) {
            $this->assertSame(
                in_array($jy, $expectedLeapYears, true),
                JalaliCalendar::isLeap($jy),
                "Leap classification of {$jy} must match the cycle embedded in the conversion functions."
            );
        }
    }

    public function test_every_day_in_1390_1430_round_trips_exactly_once(): void
    {
        $seenGregorian = [];
        $total = 0;
        for ($jy = 1390; $jy <= 1430; $jy++) {
            for ($jm = 1; $jm <= 12; $jm++) {
                $length = JalaliCalendar::monthLength($jy, $jm);
                for ($jd = 1; $jd <= $length; $jd++) {
                    $total++;
                    [$gy, $gm, $gd] = JalaliCalendar::toGregorian($jy, $jm, $jd);
                    [$ry, $rm, $rd] = JalaliCalendar::fromGregorian($gy, $gm, $gd);
                    $this->assertSame(
                        [$jy, $jm, $jd],
                        [$ry, $rm, $rd],
                        "Round trip failed for {$jy}-{$jm}-{$jd} (via {$gy}-{$gm}-{$gd})."
                    );
                    $key = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
                    $this->assertArrayNotHasKey(
                        $key,
                        $seenGregorian,
                        "Two Jalali dates map to the same Gregorian date {$key}."
                    );
                    $seenGregorian[$key] = true;
                }
            }
        }
        // 41 years with 10 leap years (1391, 1395, 1399, 1403, 1408, 1412, 1416, 1420, 1424, 1428).
        $this->assertSame(41 * 365 + 10, $total, 'Year lengths must sum to the expected day count.');
    }

    public function test_month_length_rules_and_validation(): void
    {
        $this->assertSame(31, JalaliCalendar::monthLength(1404, 1));
        $this->assertSame(31, JalaliCalendar::monthLength(1404, 6));
        $this->assertSame(30, JalaliCalendar::monthLength(1404, 7));
        $this->assertSame(30, JalaliCalendar::monthLength(1404, 11));
        $this->assertTrue(JalaliCalendar::isValid(1404, 12, 29));
        $this->assertFalse(JalaliCalendar::isValid(1404, 13, 1));
        $this->assertFalse(JalaliCalendar::isValid(1404, 0, 1));
        $this->assertFalse(JalaliCalendar::isValid(0, 1, 1));
        $this->assertFalse(JalaliCalendar::isValid(1404, 1, 32));
    }

    public function test_month_length_rejects_invalid_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JalaliCalendar::monthLength(1404, 13);
    }

    public function test_from_gregorian_rejects_impossible_dates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JalaliCalendar::fromGregorian(2025, 2, 30);
    }

    public function test_keys_labels_and_persian_digits(): void
    {
        $this->assertSame('1404-12-29', JalaliCalendar::key(1404, 12, 29));
        $this->assertSame('1404-12', JalaliCalendar::monthKey(1404, 12));
        $this->assertSame('اسفند ۱۴۰۴', JalaliCalendar::monthLabel(1404, 12));
        $this->assertSame('۱۴۰۳/۰۱/۰۱', JalaliCalendar::toPersianDigits('1403/01/01'));
        $this->assertSame('شنبه', JalaliCalendar::WEEKDAYS[0]);
        $this->assertSame(12, count(JalaliCalendar::MONTHS));
    }
}
