<?php

namespace App\Domain\Discovery\Geo;

/**
 * Great-circle distance on a spherical Earth.
 *
 * Radius is the mean Earth radius 6371.0 km (IUGG conventional mean).
 * Adequate for Tehran (~50 km metropolitan scale); not a geoid/WGS84
 * geodesic. Used after a bounding-box prefilter — never across the
 * full clinic table.
 */
final class Haversine
{
    public const EARTH_RADIUS_KM = 6371.0;

    public static function distanceKm(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng,
        float $earthRadiusKm = self::EARTH_RADIUS_KM,
    ): float {
        if ($fromLat === $toLat && $fromLng === $toLng) {
            return 0.0;
        }

        $phi1 = deg2rad($fromLat);
        $phi2 = deg2rad($toLat);
        $dPhi = deg2rad($toLat - $fromLat);
        $dLambda = deg2rad($toLng - $fromLng);

        $sinHalfPhi = sin($dPhi / 2);
        $sinHalfLambda = sin($dLambda / 2);
        $a = ($sinHalfPhi * $sinHalfPhi)
            + (cos($phi1) * cos($phi2) * $sinHalfLambda * $sinHalfLambda);
        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));

        return $earthRadiusKm * $c;
    }
}
