<?php

namespace Tests\Unit;

use App\Support\JalaliCalendar;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class JalaliCalendarTest extends TestCase
{
    public function test_known_anchors_round_trip(): void
    {
        $anchors = [
            [2024, 3, 20, 1403, 1, 1],
            [2025, 3, 20, 1403, 12, 30],
            [2025, 3, 21, 1404, 1, 1],
            [2026, 3, 20, 1404, 12, 29],
            [2026, 3, 21, 1405, 1, 1],
            [1979, 2, 11, 1357, 11, 22],
        ];

        foreach ($anchors as [$gy, $gm, $gd, $jy, $jm, $jd]) {
            $this->assertSame([$jy, $jm, $jd], JalaliCalendar::fromGregorian($gy, $gm, $gd));
            $this->assertSame([$gy, $gm, $gd], JalaliCalendar::toGregorian($jy, $jm, $jd));
        }
    }

    public function test_esfand_length_matches_the_converter_around_1403_and_1404(): void
    {
        $this->assertSame(30, JalaliCalendar::monthLength(1403, 12));
        $this->assertTrue(JalaliCalendar::isValid(1403, 12, 30));
        $this->assertSame(29, JalaliCalendar::monthLength(1404, 12));
        $this->assertFalse(JalaliCalendar::isValid(1404, 12, 30));
        $this->expectException(InvalidArgumentException::class);
        JalaliCalendar::toGregorian(1404, 12, 30);
    }

    public function test_first_eleven_months_have_fixed_lengths(): void
    {
        foreach ([1399, 1403, 1404, 1408] as $year) {
            for ($month = 1; $month <= 6; $month++) {
                $this->assertSame(31, JalaliCalendar::monthLength($year, $month));
            }
            for ($month = 7; $month <= 11; $month++) {
                $this->assertSame(30, JalaliCalendar::monthLength($year, $month));
            }
        }
    }

    public function test_every_valid_day_from_1357_through_1412_round_trips(): void
    {
        for ($year = 1357; $year <= 1412; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $length = JalaliCalendar::monthLength($year, $month);
                $this->assertContains($length, $month === 12 ? [29, 30] : [$month <= 6 ? 31 : 30]);
                for ($day = 1; $day <= $length; $day++) {
                    [$gy, $gm, $gd] = JalaliCalendar::toGregorian($year, $month, $day);
                    $this->assertSame([$year, $month, $day], JalaliCalendar::fromGregorian($gy, $gm, $gd));
                }
            }
        }
    }
}
