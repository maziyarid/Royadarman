<?php

namespace Tests\Feature;

use App\Domain\Discovery\Enums\SuitabilityStatus;
use App\Models\Clinic;
use App\Models\ClinicServiceCapability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PublicDiscoveryMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('royadarman.discovery.location_freshness_days', 180);
        config()->set('royadarman.discovery.capability_freshness_days', 180);
        config()->set('royadarman.neshan.map_api_key', '');
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

    private function attest(Clinic $clinic, SuitabilityStatus $status, bool $fresh = true): void
    {
        ClinicServiceCapability::query()->create([
            'clinic_id' => $clinic->id,
            'service_type' => 'guidance_referral',
            'suitability_status' => $status,
            'attested_at' => $fresh ? now() : now()->subDays(200),
        ]);
    }

    public function test_public_json_returns_only_match_clinics_with_coordinates(): void
    {
        $match = $this->makeClinic('Vanak Match', 35.7572, 51.4103);
        $this->attest($match, SuitabilityStatus::Suitable);
        $stale = $this->makeClinic('Stale Location', 35.7580, 51.4110, freshLocation: false);
        $this->attest($stale, SuitabilityStatus::Suitable);
        $unlocated = $this->makeClinic('Unlocated', null, null);
        $this->attest($unlocated, SuitabilityStatus::Suitable);
        $unsuitable = $this->makeClinic('Not Suitable', 35.7560, 51.4090);
        $this->attest($unsuitable, SuitabilityStatus::NotSuitable);

        $json = $this->getJson('/api/v1/public/discovery/clinics?neighborhood_id=vanak&service_type=guidance_referral')
            ->assertOk()
            ->json('data');

        $this->assertSame('vanak', $json['neighborhood_id']);
        $this->assertFalse($json['map_available']);
        $this->assertCount(1, $json['matches']);
        $this->assertSame($match->id, $json['matches'][0]['clinic_id']);
        $this->assertSame(35.7572, $json['matches'][0]['latitude']);
        $this->assertSame(51.4103, $json['matches'][0]['longitude']);
        $this->assertSame('match', $json['matches'][0]['outcome']);
        $this->assertArrayNotHasKey('available', $json['matches'][0]);
        $this->assertArrayNotHasKey('insufficient_data', $json);
        $this->assertArrayNotHasKey('not_suitable', $json);
        $ids = array_column($json['matches'], 'clinic_id');
        $this->assertNotContains($stale->id, $ids);
        $this->assertNotContains($unlocated->id, $ids);
        $this->assertNotContains($unsuitable->id, $ids);
        $this->assertStringContainsString('nshn.ir/maps', $json['matches'][0]['directions_url']);
        $this->assertStringNotContainsString('available_now', json_encode($json));
    }

    public function test_public_json_rejects_query_string_gps(): void
    {
        $this->getJson('/api/v1/public/discovery/clinics?neighborhood_id=vanak&service_type=guidance_referral&latitude=35.7&longitude=51.4')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'discovery.origin_gps_not_accepted');
    }

    public function test_referrals_page_stays_ok_without_neshan_key_and_keeps_list_fallback(): void
    {
        $clinic = $this->makeClinic('Vanak Match', 35.7572, 51.4103);
        $this->attest($clinic, SuitabilityStatus::Suitable);

        $this->get('/en/referrals')
            ->assertOk()
            ->assertSee('Vanak Match', false)
            ->assertSee('Clinic list', false)
            ->assertSee('Directions', false)
            ->assertSee('nshn.ir/maps', false)
            ->assertSee('not stored or sent to Royadarman', false)
            ->assertSee('The map is unavailable', false)
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertDontSee('available_now', false)
            ->assertDontSee('@vite', false);

        $this->get('/referrals')
            ->assertOk()
            ->assertSee('فهرست مراکز', false)
            ->assertSee('<html lang="fa" dir="rtl">', false);

        $this->get('/ar/referrals')
            ->assertOk()
            ->assertSee('قائمة العيادات', false)
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('الاتجاهات', false);
    }

    public function test_invalid_neshan_key_does_not_500_the_referrals_page(): void
    {
        config()->set('royadarman.neshan.map_api_key', 'not-a-real-key');
        $clinic = $this->makeClinic('Vanak Match', 35.7572, 51.4103);
        $this->attest($clinic, SuitabilityStatus::Suitable);

        $this->get('/en/referrals')
            ->assertOk()
            ->assertSee('Vanak Match', false)
            ->assertSee('Clinic list', false);
    }

    public function test_staff_discovery_still_omits_coordinates_on_ranking_payload(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $clinic = $this->makeClinic('Vanak Match', 35.7572, 51.4103);
        $this->attest($clinic, SuitabilityStatus::Suitable);

        $json = $this->actingAs($coordinator)
            ->getJson('/api/v1/staff/discovery/clinics?neighborhood_id=vanak&service_type=guidance_referral')
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('latitude', $json['matches'][0]);
        $this->assertArrayNotHasKey('longitude', $json['matches'][0]);
    }

    public function test_no_patient_gps_columns_postgis_or_committed_build_and_vite_entry_exists(): void
    {
        foreach (['patient_cases', 'home_service_requests', 'users'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'latitude'), $table);
            $this->assertFalse(Schema::hasColumn($table, 'longitude'), $table);
            $this->assertFalse(Schema::hasColumn($table, 'gps'), $table);
        }

        $package = File::get(base_path('package.json'));
        $this->assertStringContainsString('@neshan-maps-platform/maplibre-sdk', $package);
        $vite = File::get(base_path('vite.config.js'));
        $this->assertStringContainsString('resources/js/discovery-map.js', $vite);
        $gitignore = File::get(base_path('.gitignore'));
        $this->assertStringContainsString('/public/build', $gitignore);
        $this->assertFileExists(base_path('resources/js/discovery-map.js'));
        $this->assertStringNotContainsStringIgnoringCase('postgis', File::get(base_path('composer.json')));

        $js = File::get(base_path('resources/js/discovery-map.js'));
        $this->assertStringNotContainsString('localStorage', $js);
        $this->assertStringNotContainsString('sessionStorage', $js);
        $this->assertStringNotContainsString('sendBeacon', $js);
        $this->assertStringContainsString('searchParams.delete(key)', $js);
        $this->assertStringContainsString('activeNeighborhoodId', $js);
        $this->assertStringContainsString('select.value = activeNeighborhoodId', $js);
        $this->assertStringContainsString('serial !== requestSerial', $js);
    }
}
