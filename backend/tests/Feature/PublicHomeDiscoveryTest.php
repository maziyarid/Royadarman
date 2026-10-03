<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\ClinicServiceCapability;
use App\Models\MarketingPage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PublicHomeDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-03T12:00:00Z'));
    }

    public function test_fallback_home_binds_real_fresh_suitable_clinics_with_existing_public_payload_only(): void
    {
        $author = User::factory()->create(['name' => 'PRIVATE-ATTESTER-NAME', 'email' => 'private-attester@example.invalid', 'phone' => '09120000001']);
        [$match, $capability] = $this->clinic('eligible');
        $capability->update(['attested_by_user_id' => $author->id]);
        [$inactive] = $this->clinic('inactive');
        $inactive->update(['is_active' => false]);
        [$staleLocation] = $this->clinic('stale-location');
        $staleLocation->update(['location_recorded_at' => now()->subDays(181)]);
        [, $staleCapability] = $this->clinic('stale-capability');
        $staleCapability->update(['attested_at' => now()->subDays(181)]);
        [, $unsuitable] = $this->clinic('unsuitable');
        $unsuitable->update(['suitability_status' => 'not_suitable']);
        [, $unknown] = $this->clinic('unknown');
        $unknown->update(['suitability_status' => 'unknown']);
        [$unlocated] = $this->clinic('unlocated');
        $unlocated->update(['latitude' => null, 'longitude' => null]);
        [, $missingCapability] = $this->clinic('missing-capability');
        $missingCapability->delete();
        [, $wrongService] = $this->clinic('wrong-service');
        $wrongService->update(['service_type' => 'opg_review']);
        [$far] = $this->clinic('far');
        $far->update(['latitude' => 35.0, 'longitude' => 51.0]);
        $before = $this->snapshot();

        $response = $this->get('/en')->assertOk()->assertViewIs('public.home')->assertViewHas('discovery');
        $discovery = $response->viewData('discovery');
        $this->assertSame([$match->id], array_column($discovery['matches'], 'clinic_id'));
        $row = $discovery['matches'][0];
        $this->assertSame([
            'clinic_id', 'name', 'city', 'area_code', 'distance_km', 'outcome', 'suitability_status',
            'latitude', 'longitude', 'directions_url', 'navigation',
        ], array_keys($row));
        $this->assertSame($match->name, $row['name']);
        $this->assertSame('match', $row['outcome']);
        $this->assertSame('suitable', $row['suitability_status']);
        $this->assertSame(35.7572, $row['latitude']);
        $this->assertSame(51.4103, $row['longitude']);
        $this->assertSame(0.0, $row['distance_km']);
        $this->assertSame(['neshan', 'google', 'waze', 'balad', 'osm'], array_keys($row['navigation']));
        $payload = json_encode($discovery, JSON_THROW_ON_ERROR);
        foreach (['PRIVATE-ATTESTER-NAME', 'private-attester@example.invalid', '09120000001', 'attested_by_user_id', 'location_recorded_at', 'patient', 'storage_key'] as $private) {
            $this->assertStringNotContainsString($private, $payload);
        }
        $this->assertSame('http://localhost/api/v1/public/discovery/clinics', $discovery['endpoint']);
        $this->assertSame('guidance_referral', $discovery['service_type']);
        $this->assertSame(['lat' => 35.7572, 'lng' => 51.4103], $discovery['origin']);
        $this->assertSame($before, $this->snapshot());
        $response->assertHeader('Cache-Control', 'max-age=300, public')->assertHeader('Vary', 'Accept-Language');
    }

    public static function locales(): array
    {
        return ['Persian' => ['fa', '/'], 'Arabic' => ['ar', '/ar'], 'English' => ['en', '/en']];
    }

    #[DataProvider('locales')]
    public function test_home_projection_preserves_selected_neighborhood_and_real_locale_labels(string $locale, string $path): void
    {
        $response = $this->get($path.'?neighborhood_id=tajrish')->assertOk()->assertViewHas('discovery');
        $discovery = $response->viewData('discovery');
        $this->assertSame($locale, $response->viewData('locale'));
        $this->assertSame('tajrish', $discovery['neighborhood_id']);
        $this->assertSame(['lat' => 35.8044, 'lng' => 51.4256], $discovery['origin']);
        foreach (config('royadarman.tehran_neighborhoods') as $index => $neighborhood) {
            $this->assertSame(['id' => $neighborhood['id'], 'label' => $neighborhood[$locale]], $discovery['neighborhoods'][$index]);
        }
        $this->assertSame([], $discovery['matches']);
    }

    public static function invalidNeighborhoods(): array
    {
        return ['unknown' => ['neighborhood_id=unknown-city'], 'array' => ['neighborhood_id%5B%5D=vanak'], 'nested array' => ['neighborhood_id%5Bx%5D%5By%5D=tajrish'], 'numeric' => ['neighborhood_id=42'], 'empty' => ['neighborhood_id=']];
    }

    #[DataProvider('invalidNeighborhoods')]
    public function test_home_and_existing_referral_page_normalize_invalid_neighborhoods_to_vanak(string $query): void
    {
        foreach (['/en/referrals', '/en'] as $path) {
            $response = $this->get($path.'?'.$query)->assertOk()->assertViewHas('discovery');
            $discovery = $response->viewData('discovery');
            $this->assertSame('vanak', $discovery['neighborhood_id']);
            $this->assertSame(['lat' => 35.7572, 'lng' => 51.4103], $discovery['origin']);
            $this->assertSame([], $discovery['matches']);
        }
    }

    public function test_empty_result_stays_empty_without_fabricated_clinics_or_booking_fields(): void
    {
        $response = $this->get('/en')->assertOk()->assertViewHas('discovery');
        $this->assertSame([], $response->viewData('discovery')['matches']);
        $this->assertSame('vanak', $response->viewData('discovery')['neighborhood_id']);
        $this->assertSame(0, Clinic::query()->count());
        $this->assertStringNotContainsString('appointment', json_encode($response->viewData('discovery'), JSON_THROW_ON_ERROR));
    }

    public function test_published_cms_home_retains_precedence_without_discovery_queries_or_data(): void
    {
        $author = User::factory()->create(['role' => 'owner']);
        $page = MarketingPage::query()->create([
            'slug' => 'home', 'locale' => 'en', 'title' => 'Authored synthetic homepage',
            'body' => '<p>Authored content takes precedence.</p>', 'status' => 'published', 'published_at' => now(), 'version' => 1,
            'created_by_user_id' => $author->id, 'updated_by_user_id' => $author->id,
        ]);
        $this->clinic('not-queried');
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $response = $this->get('/en?neighborhood_id%5B%5D=vanak')->assertOk()
                ->assertViewIs('public.marketing-page')->assertViewHas('page', fn ($actual) => $actual->id === $page->id)
                ->assertSee('Authored content takes precedence.');
            $this->assertArrayNotHasKey('discovery', $response->viewData());
            foreach (DB::getQueryLog() as $query) {
                $this->assertDoesNotMatchRegularExpression('/\b(?:clinics|clinic_service_capabilities)\b/i', $query['query']);
            }
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    private function clinic(string $label): array
    {
        $clinic = Clinic::query()->create([
            'name' => 'Synthetic '.Str::ulid().' '.$label, 'city' => 'Tehran', 'area_code' => 'north', 'is_active' => true,
            'latitude' => 35.7572, 'longitude' => 51.4103, 'location_recorded_at' => now()->subDay(),
        ]);
        $capability = ClinicServiceCapability::query()->create([
            'clinic_id' => $clinic->id, 'service_type' => 'guidance_referral', 'suitability_status' => 'suitable', 'attested_at' => now()->subDay(),
        ]);

        return [$clinic, $capability];
    }

    private function snapshot(): array
    {
        return collect(['clinics', 'clinic_service_capabilities', 'patient_cases', 'audit_events', 'outbox_events'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }
}
