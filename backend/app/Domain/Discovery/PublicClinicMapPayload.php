<?php

namespace App\Domain\Discovery;

use App\Domain\Discovery\Enums\DiscoveryOutcome;
use App\Domain\Discovery\ValueObjects\DiscoveryCandidate;
use App\Domain\Discovery\ValueObjects\DiscoveryResult;

final class PublicClinicMapPayload
{
    /**
     * Public map/list payload: matches with fresh plottable coordinates only.
     * Insufficient/stale/unlocated/not-suitable rows are omitted — never plotted
     * and never described as available.
     *
     * @param  array{id: string, area: string, lat: float, lng: float}  $origin
     * @return array<string, mixed>
     */
    public static function fromResult(DiscoveryResult $result, array $origin, bool $mapAvailable): array
    {
        $matches = [];
        foreach ($result->matches as $candidate) {
            $row = self::matchRow($candidate, $origin);
            if ($row !== null) {
                $matches[] = $row;
            }
        }

        return [
            'neighborhood_id' => $result->neighborhoodId,
            'service_type' => $result->serviceType,
            'radius_km' => $result->radiusKm,
            'origin' => [
                'lat' => $origin['lat'],
                'lng' => $origin['lng'],
            ],
            'map_available' => $mapAvailable,
            'matches' => $matches,
        ];
    }

    /**
     * @param  array{lat: float, lng: float}  $origin
     * @return array<string, mixed>|null
     */
    public static function matchRow(DiscoveryCandidate $candidate, array $origin): ?array
    {
        if ($candidate->outcome !== DiscoveryOutcome::Match || ! $candidate->hasPlottableCoordinates()) {
            return null;
        }

        $lat = (float) $candidate->latitude;
        $lng = (float) $candidate->longitude;

        return [
            'clinic_id' => $candidate->clinicId,
            'name' => $candidate->name,
            'city' => $candidate->city,
            'area_code' => $candidate->areaCode,
            'distance_km' => round($candidate->distanceKm, 3),
            'outcome' => $candidate->outcome->value,
            'suitability_status' => $candidate->suitabilityStatus?->value,
            'latitude' => $lat,
            'longitude' => $lng,
            'directions_url' => NeshanDirectionsUrl::drive(
                $origin['lat'],
                $origin['lng'],
                $lat,
                $lng,
            ),
        ];
    }
}
