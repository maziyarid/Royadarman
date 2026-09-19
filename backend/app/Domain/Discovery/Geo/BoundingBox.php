<?php

namespace App\Domain\Discovery\Geo;

/**
 * Axis-aligned lat/lng window that is a superset of a great-circle radius.
 *
 * The degree conversion is coupled to the same 6371 km sphere used by
 * Haversine so this box remains an enclosing superset of the search circle.
 * Longitude degrees shrink by cos(lat). Candidates outside this box cannot
 * lie inside the circle, so they are excluded before any Haversine evaluation.
 */
final readonly class BoundingBox
{
    public const KM_PER_DEGREE_LATITUDE = 111.19492664455873;

    public function __construct(
        public float $minLat,
        public float $maxLat,
        public float $minLng,
        public float $maxLng,
    ) {}

    public static function fromOrigin(float $lat, float $lng, float $radiusKm, float $kmPerDegreeLat = self::KM_PER_DEGREE_LATITUDE): self
    {
        $latDelta = $radiusKm / $kmPerDegreeLat;
        $cos = cos(deg2rad($lat));
        $lngDenom = $kmPerDegreeLat * max(abs($cos), 0.000001);
        $lngDelta = $radiusKm / $lngDenom;

        return new self(
            max(-90.0, $lat - $latDelta),
            min(90.0, $lat + $latDelta),
            max(-180.0, $lng - $lngDelta),
            min(180.0, $lng + $lngDelta),
        );
    }

    public function contains(float $lat, float $lng): bool
    {
        return $lat >= $this->minLat && $lat <= $this->maxLat
            && $lng >= $this->minLng && $lng <= $this->maxLng;
    }
}
