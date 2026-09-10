<?php

namespace App\Support;

final class DigitNormalizer
{
    private const SOURCE = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const TARGET = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public static function latin(string $value): string
    {
        return str_replace(self::SOURCE, self::TARGET, $value);
    }

    public static function iranianMobile(string $value): string
    {
        $digits = (string) preg_replace('/\D+/', '', self::latin($value));

        return (string) preg_replace('/^(?:0098|98)/', '0', $digits);
    }
}
