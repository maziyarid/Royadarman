<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cases\Enums\ServiceType;
use App\Domain\Discovery\NeshanMapConfig;
use App\Domain\Discovery\PublicClinicMapPayload;
use App\Domain\Discovery\Services\TehranSuitabilityDiscovery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PublicDiscoveryController extends Controller
{
    public function __construct(private readonly TehranSuitabilityDiscovery $discovery) {}

    public function clinics(Request $request): JsonResponse
    {
        if ($request->hasAny(['latitude', 'longitude', 'lat', 'lng', 'gps', 'origin_lat', 'origin_lng'])) {
            return response()->json([
                'error' => ['code' => 'discovery.origin_gps_not_accepted'],
                'request_id' => $request->attributes->get('request_id'),
            ], 422);
        }

        $ids = array_column(config('royadarman.tehran_neighborhoods', []), 'id');
        $data = $request->validate([
            'neighborhood_id' => ['required', 'string', Rule::in($ids)],
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'radius_km' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $neighborhoodId = $data['neighborhood_id'];
        $origin = $this->discovery->neighborhoodOrigin($neighborhoodId);
        $result = $this->discovery->search(
            $neighborhoodId,
            ServiceType::from($data['service_type']),
            isset($data['radius_km']) ? (float) $data['radius_km'] : null,
        );

        return response()->json([
            'data' => PublicClinicMapPayload::fromResult(
                $result,
                $origin,
                NeshanMapConfig::enabled(),
            ),
        ]);
    }
}
