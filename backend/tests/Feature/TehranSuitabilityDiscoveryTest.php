<?php

namespace Tests\Feature;

use App\Domain\Cases\Enums\ServiceType;
use App\Domain\Discovery\Enums\DiscoveryOutcome;
use App\Domain\Discovery\Enums\SuitabilityStatus;
use App\Domain\Discovery\Services\TehranSuitabilityDiscovery;
use App\Models\Clinic;
use App\Models\ClinicServiceCapability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class TehranSuitabilityDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('royadarman.discovery.location_freshness_days', 180);
        config()->set('royadarman.discovery.capability_freshness_days', 180);
        config()->set('royadarman.discovery.default_radius_km', 15);
        config()->set('royadarman.discovery.max_radius_km', 40);
    }

    private function makeClinic(string $name, ?float $lat, ?float $lng, bool $freshLocation = true): Clinic
    {
        return Clinic::query()->create([
            'name' => $name,
            'city' => 'Tehran',
            'area_code' => 'north',
            'is_active' => true,
            'latitude' => $lat,
            'longitude' => $lng,
            'location_recorded_at' => ($lat === null || $lng === null) ? null : ($freshLocation ? now() : now()->subDays(200)),
        ]);
    }

    private function attest(Clinic $clinic, SuitabilityStatus $status, bool $fresh = true, string $service = 'guidance_referral'): void
    {
        ClinicServiceCapability::query()->create([
            'clinic_id' => $clinic->id,
            'service_type' => $service,
            'suitability_status' => $status,
            'attested_at' => $fresh ? now() : now()->subDays(200),
        ]);
    }

    public function test_bbox_prefilter_skips_far_clinics_before_haversine(): void
    {
        $near = $this->makeClinic('Vanak Clinic', 35.7572, 51.4103);
        $this->attest($near, SuitabilityStatus::Suitable);
        $far = $this->makeClinic('Isfahan Clinic', 32.6546, 51.6680);
        $this->attest($far, SuitabilityStatus::Suitable);

        $discovery = app(TehranSuitabilityDiscovery::class);
        $result = $discovery->search('vanak', ServiceType::GuidanceReferral, 15.0);

        $this->assertSame(1, $discovery->lastFilterStats['bounded']);
        $this->assertSame(1, $discovery->lastFilterStats['haversine']);
        $this->assertCount(1, $result->matches);
        $this->assertSame($near->id, $result->matches[0]->clinicId);
        $this->assertEqualsWithDelta(0.0, $result->matches[0]->distanceKm, 0.001);
        $this->assertSame(DiscoveryOutcome::Match, $result->matches[0]->outcome);
        $ids = array_map(fn ($c) => $c->clinicId, [...$result->matches, ...$result->insufficientData, ...$result->notSuitable]);
        $this->assertNotContains($far->id, $ids);
    }

    public function test_missing_location_is_never_a_match(): void
    {
        $clinic = $this->makeClinic('Unlocated', null, null);
        $this->attest($clinic, SuitabilityStatus::Suitable);

        $result = app(TehranSuitabilityDiscovery::class)->search('vanak', ServiceType::GuidanceReferral, 15.0);

        $this->assertSame([], $result->matches);
        $this->assertSame([], $result->insufficientData);
    }

    public function test_stale_location_is_insufficient_data_not_a_match(): void
    {
        $clinic = $this->makeClinic('Stale location', 35.7572, 51.4103, freshLocation: false);
        $this->attest($clinic, SuitabilityStatus::Suitable);

        $result = app(TehranSuitabilityDiscovery::class)->search('vanak', ServiceType::GuidanceReferral, 15.0);

        $this->assertCount(0, $result->matches);
        $this->assertCount(1, $result->insufficientData);
        $this->assertSame(DiscoveryOutcome::InsufficientData, $result->insufficientData[0]->outcome);
        $this->assertFalse($result->insufficientData[0]->locationFresh);
    }

    public function test_missing_unknown_or_stale_capability_is_insufficient_data(): void
    {
        $missing = $this->makeClinic('No capability', 35.7572, 51.4103);
        $unknown = $this->makeClinic('Unknown capability', 35.7580, 51.4110);
        $this->attest($unknown, SuitabilityStatus::Unknown);
        $stale = $this->makeClinic('Stale capability', 35.7560, 51.4090);
        $this->attest($stale, SuitabilityStatus::Suitable, fresh: false);

        $result = app(TehranSuitabilityDiscovery::class)->search('vanak', ServiceType::GuidanceReferral, 15.0);

        $this->assertCount(0, $result->matches);
        $this->assertCount(3, $result->insufficientData);
        foreach ($result->insufficientData as $row) {
            $this->assertSame(DiscoveryOutcome::InsufficientData, $row->outcome);
        }
    }

    public function test_not_suitable_is_explicit_and_not_a_match(): void
    {
        $clinic = $this->makeClinic('Not for this service', 35.7572, 51.4103);
        $this->attest($clinic, SuitabilityStatus::NotSuitable);

        $result = app(TehranSuitabilityDiscovery::class)->search('vanak', ServiceType::GuidanceReferral, 15.0);

        $this->assertCount(0, $result->matches);
        $this->assertCount(1, $result->notSuitable);
        $this->assertSame(DiscoveryOutcome::NotSuitable, $result->notSuitable[0]->outcome);
        $encoded = json_encode($result->toArray());
        $this->assertStringNotContainsString('available', $encoded);
        $this->assertStringNotContainsString('open_slot', $encoded);
        $this->assertStringNotContainsString('available_now', $encoded);
    }

    public function test_matches_sort_by_distance_then_id(): void
    {
        $farther = $this->makeClinic('Farther', 35.7700, 51.4103);
        $this->attest($farther, SuitabilityStatus::Suitable);
        $closer = $this->makeClinic('Closer', 35.7580, 51.4103);
        $this->attest($closer, SuitabilityStatus::Suitable);

        $result = app(TehranSuitabilityDiscovery::class)->search('vanak', ServiceType::GuidanceReferral, 15.0);

        $this->assertCount(2, $result->matches);
        $this->assertSame($closer->id, $result->matches[0]->clinicId);
        $this->assertSame($farther->id, $result->matches[1]->clinicId);
        $this->assertLessThan($result->matches[1]->distanceKm, $result->matches[0]->distanceKm);
    }

    public function test_coordinator_can_search_by_neighborhood_id_only(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $clinic = $this->makeClinic('Vanak Clinic', 35.7572, 51.4103);
        $this->attest($clinic, SuitabilityStatus::Suitable);

        $json = $this->actingAs($coordinator)
            ->getJson('/api/v1/staff/discovery/clinics?neighborhood_id=vanak&service_type=guidance_referral')
            ->assertOk()
            ->json('data');

        $this->assertSame('vanak', $json['neighborhood_id']);
        $this->assertEquals(6371.0, $json['earth_radius_km']);
        $this->assertCount(1, $json['matches']);
        $this->assertArrayNotHasKey('latitude', $json);
        $this->assertArrayNotHasKey('longitude', $json);
        $this->assertArrayNotHasKey('latitude', $json['matches'][0]);
        $this->assertArrayNotHasKey('longitude', $json['matches'][0]);
        $this->assertArrayNotHasKey('available', $json['matches'][0]);
        $this->assertSame('match', $json['matches'][0]['outcome']);
    }

    public function test_query_string_gps_is_rejected_and_patients_cannot_search(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($coordinator)
            ->getJson('/api/v1/staff/discovery/clinics?neighborhood_id=vanak&service_type=guidance_referral&latitude=35.7&longitude=51.4')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'discovery.origin_gps_not_accepted');

        $this->actingAs($patient)
            ->getJson('/api/v1/staff/discovery/clinics?neighborhood_id=vanak&service_type=guidance_referral')
            ->assertNotFound();

        $this->actingAs($owner)
            ->getJson('/api/v1/staff/discovery/clinics?neighborhood_id=vanak&service_type=guidance_referral')
            ->assertNotFound();

        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->actingAs($clinician)
            ->getJson('/api/v1/staff/discovery/clinics?neighborhood_id=vanak&service_type=guidance_referral')
            ->assertNotFound();
    }

    public function test_inactive_clinic_is_never_a_match(): void
    {
        $clinic = $this->makeClinic('Inactive nearby', 35.7572, 51.4103);
        $this->attest($clinic, SuitabilityStatus::Suitable);
        $clinic->forceFill(['is_active' => false])->save();

        $result = app(TehranSuitabilityDiscovery::class)->search('vanak', ServiceType::GuidanceReferral, 15.0);

        $this->assertSame([], $result->matches);
        $this->assertSame([], $result->insufficientData);
        $this->assertSame([], $result->notSuitable);
    }

    public function test_schema_has_clinic_geo_but_no_patient_or_home_service_gps(): void
    {
        $this->assertTrue(Schema::hasColumn('clinics', 'latitude'));
        $this->assertTrue(Schema::hasColumn('clinics', 'longitude'));
        $this->assertTrue(Schema::hasColumn('clinics', 'location_recorded_at'));
        $this->assertTrue(Schema::hasTable('clinic_service_capabilities'));
        $this->assertFalse(Schema::hasColumn('clinic_service_capabilities', 'patient_latitude'));
        foreach (['patient_cases', 'home_service_requests', 'users'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'latitude'), $table);
            $this->assertFalse(Schema::hasColumn($table, 'longitude'), $table);
            $this->assertFalse(Schema::hasColumn($table, 'gps'), $table);
        }

        $composer = File::get(base_path('composer.json'));
        $this->assertStringNotContainsStringIgnoringCase('postgis', $composer);
        $this->assertStringNotContainsStringIgnoringCase('pgsql', $composer);
    }

    public function test_owner_can_attest_location_and_capability_without_patient_gps(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);

        $this->actingAs($owner)->post('/en/panel/network/clinics', [
            'name' => 'Attested Clinic',
            'city' => 'Tehran',
            'area_code' => 'north',
            'latitude' => '35.757200',
            'longitude' => '51.410300',
        ])->assertRedirect();

        $clinicId = Clinic::query()->where('name', 'Attested Clinic')->value('id');
        $this->assertNotNull($clinicId);
        $this->assertNotNull(Clinic::query()->find($clinicId)->location_recorded_at);

        $this->actingAs($owner)->post('/en/panel/network/capabilities', [
            'clinic_id' => $clinicId,
            'service_type' => 'guidance_referral',
            'suitability_status' => 'suitable',
        ])->assertRedirect();

        $this->assertDatabaseHas('clinic_service_capabilities', [
            'clinic_id' => $clinicId,
            'service_type' => 'guidance_referral',
            'suitability_status' => 'suitable',
            'attested_by_user_id' => $owner->id,
        ]);
        $this->assertFalse(Schema::hasColumn('patient_cases', 'latitude'));
    }
}
