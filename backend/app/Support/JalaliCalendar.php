<?php

namespace App\Support;

use InvalidArgumentException;

/** Persian calendar conversion for display and query boundaries. Persistence remains UTC/Gregorian. */
final class JalaliCalendar
{
    public const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    public const WEEKDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    private const GREGORIAN_DAYS_BEFORE_MONTH = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    /** @return array{0:int,1:int,2:int} */
    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        if (! checkdate($gm, $gd, $gy)) {
            throw new InvalidArgumentException('Invalid Gregorian date.');
        }
        $jy = $gy <= 1600 ? 0 : 979;
        $gy -= $gy <= 1600 ? 621 : 1600;
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400) - 80 + $gd + self::GREGORIAN_DAYS_BEFORE_MONTH[$gm - 1];
        $jy += 33 * intdiv($days, 12053);
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            return [$jy, 1 + intdiv($days, 31), 1 + ($days % 31)];
        }

        return [$jy, 7 + intdiv($days - 186, 30), 1 + (($days - 186) % 30)];
    }

    /** @return array{0:int,1:int,2:int} */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        if (! self::isValid($jy, $jm, $jd)) {
            throw new InvalidArgumentException('Invalid Jalali date.');
        }
        $gy = $jy <= 979 ? 621 : 1600;
        $year = $jy - ($jy <= 979 ? 0 : 979);
        $days = (365 * $year) + (intdiv($year, 33) * 8) + intdiv(($year % 33) + 3, 4) + 78 + $jd
            + ($jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy += 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $days--;
            $gy += 100 * intdiv($days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $monthLengths = [0, 31, self::isGregorianLeap($gy) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 1; $gm <= 12 && $gd > $monthLengths[$gm]; $gm++) {
            $gd -= $monthLengths[$gm];
        }

        return [$gy, $gm, $gd];
    }

    private const LEAP_CYCLE_YEARS = [1, 5, 9, 13, 17, 22, 26, 30];

    public static function monthLength(int $jy, int $jm): int
    {
        if ($jm < 1 || $jm > 12 || $jy < 1) {
            throw new InvalidArgumentException('Invalid Jalali month.');
        }
        if ($jm <= 6) {
            return 31;
        }
        if ($jm <= 11) {
            return 30;
        }

        return self::isLeap($jy) ? 30 : 29;
    }

    /** Same 33-year leap cycle that fromGregorian/toGregorian embed, so validity, month grids and conversions cannot disagree. */
    public static function isLeap(int $jy): bool
    {
        if ($jy < 1) {
            throw new InvalidArgumentException('Invalid Jalali year.');
        }

        return in_array($jy % 33, self::LEAP_CYCLE_YEARS, true);
    }

    public static function isValid(int $jy, int $jm, int $jd): bool
    {
        return $jy >= 1 && $jm >= 1 && $jm <= 12 && $jd >= 1 && $jd <= self::monthLength($jy, $jm);
    }

    public static function key(int $jy, int $jm, int $jd): string
    {
        return sprintf('%04d-%02d-%02d', $jy, $jm, $jd);
    }

    public static function monthKey(int $jy, int $jm): string
    {
        return sprintf('%04d-%02d', $jy, $jm);
    }

    public static function monthLabel(int $jy, int $jm): string
    {
        if ($jm < 1 || $jm > 12) {
            throw new InvalidArgumentException('Invalid Jalali month.');
        }

        return self::toPersianDigits(self::MONTHS[$jm - 1].' '.$jy);
    }

    public static function toPersianDigits(string $value): string
    {
        return strtr($value, array_combine(range('0', '9'), self::PERSIAN_DIGITS));
    }

    private static function isGregorianLeap(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }
}
