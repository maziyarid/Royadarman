<?php

namespace App\Domain\Discovery\ValueObjects;

use App\Domain\Discovery\Enums\DiscoveryOutcome;
use App\Domain\Discovery\Enums\SuitabilityStatus;

final readonly class DiscoveryCandidate
{
    public function __construct(
        public string $clinicId,
        public string $name,
        public string $city,
        public ?string $areaCode,
        public ?float $distanceKm,
        public DiscoveryOutcome $outcome,
        public ?SuitabilityStatus $suitabilityStatus,
        public bool $locationFresh,
        public bool $capabilityFresh,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}

    public function hasPlottableCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'clinic_id' => $this->clinicId,
            'name' => $this->name,
            'city' => $this->city,
            'area_code' => $this->areaCode,
            'distance_km' => $this->distanceKm === null ? null : round($this->distanceKm, 3),
            'outcome' => $this->outcome->value,
            'suitability_status' => $this->suitabilityStatus?->value,
            'location_fresh' => $this->locationFresh,
            'capability_fresh' => $this->capabilityFresh,
        ];
    }
}
