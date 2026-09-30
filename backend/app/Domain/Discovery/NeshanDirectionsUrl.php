<?php

namespace App\Domain\Discovery;

/**
 * Public Neshan maps URL for driving directions.
 * Origin is a published neighbourhood centroid or an ephemeral browser point —
 * never a stored patient coordinate.
 */
final class NeshanDirectionsUrl
{
    public static function drive(float $originLat, float $originLng, float $destinationLat, float $destinationLng): string
    {
        return sprintf(
            'https://nshn.ir/maps?origin=%s,%s&destination=%s,%s&type=drive',
            self::coord($originLat),
            self::coord($originLng),
            self::coord($destinationLat),
            self::coord($destinationLng),
        );
    }

    private static function coord(float $value): string
    {
        return rtrim(rtrim(sprintf('%.6f', $value), '0'), '.');
    }
}
