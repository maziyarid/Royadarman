<?php

namespace Tests\Unit;

use App\Domain\Discovery\Enums\DiscoveryOutcome;
use App\Domain\Discovery\Enums\SuitabilityStatus;
use App\Domain\Discovery\PublicClinicMapPayload;
use App\Domain\Discovery\ValueObjects\DiscoveryCandidate;
use App\Domain\Discovery\ValueObjects\DiscoveryResult;
use Tests\TestCase;

final class PublicClinicMapPayloadTest extends TestCase
{
    public function test_payload_plots_only_match_rows_with_coordinates(): void
    {
        $origin = ['id' => 'vanak', 'area' => 'north', 'lat' => 35.7572, 'lng' => 51.4103];
        $match = new DiscoveryCandidate(
            clinicId: '01MATCH',
            name: 'Vanak Clinic',
            city: 'Tehran',
            areaCode: 'north',
            distanceKm: 0.4,
            outcome: DiscoveryOutcome::Match,
            suitabilityStatus: SuitabilityStatus::Suitable,
            locationFresh: true,
            capabilityFresh: true,
            latitude: 35.7580,
            longitude: 51.4110,
        );
        $stale = new DiscoveryCandidate(
            clinicId: '01STALE',
            name: 'Stale Clinic',
            city: 'Tehran',
            areaCode: 'north',
            distanceKm: 0.5,
            outcome: DiscoveryOutcome::InsufficientData,
            suitabilityStatus: SuitabilityStatus::Suitable,
            locationFresh: false,
            capabilityFresh: true,
            latitude: 35.7590,
            longitude: 51.4120,
        );
        $unlocated = new DiscoveryCandidate(
            clinicId: '01NONE',
            name: 'No coords',
            city: 'Tehran',
            areaCode: 'north',
            distanceKm: 0.1,
            outcome: DiscoveryOutcome::Match,
            suitabilityStatus: SuitabilityStatus::Suitable,
            locationFresh: true,
            capabilityFresh: true,
            latitude: null,
            longitude: null,
        );

        $payload = PublicClinicMapPayload::fromResult(
            new DiscoveryResult(
                neighborhoodId: 'vanak',
                serviceType: 'guidance_referral',
                radiusKm: 15.0,
                matches: [$match, $unlocated],
                insufficientData: [$stale],
                notSuitable: [],
                filterStats: ['bounded' => 3, 'haversine' => 3],
            ),
            $origin,
            false,
        );

        $this->assertFalse($payload['map_available']);
        $this->assertCount(1, $payload['matches']);
        $this->assertSame('01MATCH', $payload['matches'][0]['clinic_id']);
        $this->assertSame(35.7580, $payload['matches'][0]['latitude']);
        $this->assertSame(51.4110, $payload['matches'][0]['longitude']);
        $this->assertArrayNotHasKey('insufficient_data', $payload);
        $this->assertArrayNotHasKey('not_suitable', $payload);
        $this->assertArrayNotHasKey('available', $payload['matches'][0]);
        $encoded = json_encode($payload);
        $this->assertStringNotContainsString('available_now', $encoded);
        $this->assertStringNotContainsString('open_slot', $encoded);
        $this->assertStringContainsString('nshn.ir/maps', $payload['matches'][0]['directions_url']);
    }
}
