<?php

namespace App\Domain\Discovery\Services;

use App\Domain\Cases\Enums\ServiceType;
use App\Domain\Discovery\Enums\DiscoveryOutcome;
use App\Domain\Discovery\Enums\SuitabilityStatus;
use App\Domain\Discovery\Geo\BoundingBox;
use App\Domain\Discovery\Geo\Haversine;
use App\Domain\Discovery\ValueObjects\DiscoveryCandidate;
use App\Domain\Discovery\ValueObjects\DiscoveryResult;
use App\Models\Clinic;
use App\Support\DomainException;
use DateTimeInterface;
use Illuminate\Support\Carbon;

final class TehranSuitabilityDiscovery
{
    /**
     * Stats for the most recent search. `bounded` is rows that passed the
     * lat/lng box; `haversine` is how many of those were distance-evaluated.
     * Far clinics never increment haversine.
     *
     * @var array{bounded: int, haversine: int}
     */
    public array $lastFilterStats = ['bounded' => 0, 'haversine' => 0];

    public function search(
        string $neighborhoodId,
        ServiceType $serviceType,
        ?float $radiusKm = null,
        ?DateTimeInterface $now = null,
    ): DiscoveryResult {
        $origin = $this->neighborhoodOrigin($neighborhoodId);
        $radius = $this->normalizedRadius($radiusKm);
        $at = Carbon::parse($now ?? now());
        $box = BoundingBox::fromOrigin($origin['lat'], $origin['lng'], $radius);

        $bounded = Clinic::query()
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$box->minLat, $box->maxLat])
            ->whereBetween('longitude', [$box->minLng, $box->maxLng])
            ->with(['serviceCapabilities' => fn ($q) => $q->where('service_type', $serviceType->value)])
            ->orderBy('id')
            ->get();

        $this->lastFilterStats = ['bounded' => $bounded->count(), 'haversine' => 0];

        $matches = [];
        $insufficient = [];
        $notSuitable = [];

        $locationDays = (int) config('royadarman.discovery.location_freshness_days', 180);
        $capabilityDays = (int) config('royadarman.discovery.capability_freshness_days', 180);

        foreach ($bounded as $clinic) {
            $lat = (float) $clinic->latitude;
            $lng = (float) $clinic->longitude;
            if (! $box->contains($lat, $lng)) {
                continue;
            }

            $this->lastFilterStats['haversine']++;
            $distance = Haversine::distanceKm($origin['lat'], $origin['lng'], $lat, $lng);
            if ($distance > $radius) {
                continue;
            }

            $locationFresh = $clinic->location_recorded_at instanceof DateTimeInterface
                && $clinic->location_recorded_at->gte($at->copy()->subDays($locationDays));

            $capability = $clinic->serviceCapabilities->first();
            $rawStatus = $capability?->suitability_status;
            $status = match (true) {
                $rawStatus instanceof SuitabilityStatus => $rawStatus,
                is_string($rawStatus) => SuitabilityStatus::tryFrom($rawStatus),
                default => null,
            };
            $capabilityFresh = $capability !== null
                && $capability->attested_at instanceof DateTimeInterface
                && $capability->attested_at->gte($at->copy()->subDays($capabilityDays));

            if (! $locationFresh || $status === null || $status === SuitabilityStatus::Unknown || ! $capabilityFresh) {
                $insufficient[] = new DiscoveryCandidate(
                    clinicId: $clinic->id,
                    name: $clinic->name,
                    city: $clinic->city,
                    areaCode: $clinic->area_code,
                    distanceKm: $distance,
                    outcome: DiscoveryOutcome::InsufficientData,
                    suitabilityStatus: $status,
                    locationFresh: $locationFresh,
                    capabilityFresh: $capabilityFresh,
                    latitude: $lat,
                    longitude: $lng,
                );

                continue;
            }

            if ($status === SuitabilityStatus::NotSuitable) {
                $notSuitable[] = new DiscoveryCandidate(
                    clinicId: $clinic->id,
                    name: $clinic->name,
                    city: $clinic->city,
                    areaCode: $clinic->area_code,
                    distanceKm: $distance,
                    outcome: DiscoveryOutcome::NotSuitable,
                    suitabilityStatus: $status,
                    locationFresh: $locationFresh,
                    capabilityFresh: $capabilityFresh,
                    latitude: $lat,
                    longitude: $lng,
                );

                continue;
            }

            $matches[] = new DiscoveryCandidate(
                clinicId: $clinic->id,
                name: $clinic->name,
                city: $clinic->city,
                areaCode: $clinic->area_code,
                distanceKm: $distance,
                outcome: DiscoveryOutcome::Match,
                suitabilityStatus: $status,
                locationFresh: true,
                capabilityFresh: true,
                latitude: $lat,
                longitude: $lng,
            );
        }

        usort($matches, $this->stableByDistance(...));
        usort($insufficient, $this->stableByDistance(...));
        usort($notSuitable, $this->stableByDistance(...));

        return new DiscoveryResult(
            neighborhoodId: $neighborhoodId,
            serviceType: $serviceType->value,
            radiusKm: $radius,
            matches: $matches,
            insufficientData: $insufficient,
            notSuitable: $notSuitable,
            filterStats: $this->lastFilterStats,
        );
    }

    /** @return array{id: string, area: string, lat: float, lng: float} */
    public function neighborhoodOrigin(string $neighborhoodId): array
    {
        foreach (config('royadarman.tehran_neighborhoods', []) as $n) {
            if (($n['id'] ?? '') !== $neighborhoodId) {
                continue;
            }
            if (! isset($n['lat'], $n['lng'])) {
                throw new DomainException(422, 'discovery.neighborhood_origin_missing');
            }

            return [
                'id' => (string) $n['id'],
                'area' => (string) ($n['area'] ?? ''),
                'lat' => (float) $n['lat'],
                'lng' => (float) $n['lng'],
            ];
        }

        throw new DomainException(422, 'discovery.neighborhood_unknown');
    }

    private function normalizedRadius(?float $radiusKm): float
    {
        $default = (float) config('royadarman.discovery.default_radius_km', 15);
        $max = (float) config('royadarman.discovery.max_radius_km', 40);
        $radius = $radiusKm ?? $default;
        if ($radius <= 0 || $radius > $max) {
            throw new DomainException(422, 'discovery.radius_invalid');
        }

        return $radius;
    }

    private function stableByDistance(DiscoveryCandidate $a, DiscoveryCandidate $b): int
    {
        $cmp = $a->distanceKm <=> $b->distanceKm;
        if ($cmp !== 0) {
            return $cmp;
        }

        return $a->clinicId <=> $b->clinicId;
    }
}
