<?php

namespace App\Domain\Discovery\ValueObjects;

use App\Domain\Discovery\Geo\Haversine;

final readonly class DiscoveryResult
{
    /**
     * @param  list<DiscoveryCandidate>  $matches
     * @param  list<DiscoveryCandidate>  $insufficientData
     * @param  list<DiscoveryCandidate>  $notSuitable
     * @param  array{bounded: int, haversine: int}  $filterStats
     */
    public function __construct(
        public string $neighborhoodId,
        public string $serviceType,
        public float $radiusKm,
        public array $matches,
        public array $insufficientData,
        public array $notSuitable,
        public array $filterStats,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'neighborhood_id' => $this->neighborhoodId,
            'service_type' => $this->serviceType,
            'radius_km' => $this->radiusKm,
            'earth_radius_km' => Haversine::EARTH_RADIUS_KM,
            'matches' => array_map(fn (DiscoveryCandidate $c) => $c->toArray(), $this->matches),
            'insufficient_data' => array_map(fn (DiscoveryCandidate $c) => $c->toArray(), $this->insufficientData),
            'not_suitable' => array_map(fn (DiscoveryCandidate $c) => $c->toArray(), $this->notSuitable),
        ];
    }
}
