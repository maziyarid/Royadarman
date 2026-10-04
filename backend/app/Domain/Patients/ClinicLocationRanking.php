<?php

namespace App\Domain\Patients;

use App\Domain\Discovery\Enums\SuitabilityStatus;
use App\Domain\Discovery\Geo\Haversine;
use App\Models\CaseLocation;
use App\Models\Clinic;
use App\Models\PatientCase;
use Illuminate\Support\Collection;

final class ClinicLocationRanking
{
    public function forCase(PatientCase $case): Collection
    {
        $origin = CaseLocation::query()->find($case->id);
        $normalizer = app(IranLocations::class);
        $hasPreciseOrigin = $origin?->location_consented_at !== null
            && $origin->latitude !== null
            && $origin->longitude !== null;

        return Clinic::query()
            ->where('is_active', true)
            ->whereNull('synthetic_demo_key')
            ->with(['serviceCapabilities' => fn ($query) => $query->where('service_type', $case->service_type->value)])
            ->orderBy('name')
            ->get()
            ->map(function (Clinic $clinic) use ($origin, $hasPreciseOrigin, $normalizer): ?object {
                $capability = $clinic->serviceCapabilities->first();
                $status = $capability?->suitability_status;
                $status = $status instanceof SuitabilityStatus ? $status : (is_string($status) ? SuitabilityStatus::tryFrom($status) : null);

                if ($status === SuitabilityStatus::NotSuitable) {
                    return null;
                }

                $capabilityFresh = $capability?->attested_at !== null
                    && $capability->attested_at->gte(now()->subDays((int) config('royadarman.discovery.capability_freshness_days', 180)));
                $serviceVerified = $status === SuitabilityStatus::Suitable && $capabilityFresh;

                $locationFresh = $clinic->location_recorded_at !== null
                    && $clinic->location_recorded_at->gte(now()->subDays((int) config('royadarman.discovery.location_freshness_days', 180)));

                $distanceKm = null;
                if ($hasPreciseOrigin && $locationFresh
                    && $clinic->latitude !== null && $clinic->longitude !== null
                    && $clinic->latitude >= 24 && $clinic->latitude <= 41
                    && $clinic->longitude >= 43 && $clinic->longitude <= 64) {
                    $distanceKm = Haversine::distanceKm(
                        (float) $origin->latitude,
                        (float) $origin->longitude,
                        (float) $clinic->latitude,
                        (float) $clinic->longitude,
                    );
                }

                return (object) [
                    'id' => $clinic->id,
                    'name' => $clinic->name,
                    'city' => $clinic->city,
                    'area_code' => $clinic->area_code,
                    'distance_km' => $distanceKm,
                    'same_city' => $origin !== null
                        && $normalizer->normalize((string) $origin->city) === $normalizer->normalize((string) $clinic->city),
                    'service_verified' => $serviceVerified,
                ];
            })
            ->filter()
            ->sort(function (object $left, object $right): int {
                return ((int) $right->service_verified <=> (int) $left->service_verified)
                    ?: (($left->distance_km ?? INF) <=> ($right->distance_km ?? INF))
                    ?: ((int) $right->same_city <=> (int) $left->same_city)
                    ?: strcmp($left->name, $right->name)
                    ?: strcmp($left->id, $right->id);
            })
            ->values();
    }
}
