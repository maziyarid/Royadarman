<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\ClinicServiceCapability;
use App\Models\MarketingPage;
use App\Models\User;
use App\Support\PublicUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicHomePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_uses_the_existing_brand_instead_of_a_placeholder_city_photo(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('class="public-home-brand-mark"', $html);
        $this->assertStringContainsString('src="/assets/brand-mark.svg"', $html);
        $this->assertStringNotContainsString('/assets/photos/tehran', $html);
        $this->assertSame(1, preg_match_all('/<h1\b/', $html));
        $this->assertStringContainsString('href="/assets/public-home.css?v=20261003"', $html);
    }

    public function test_all_locales_keep_actual_navigation_search_and_service_bindings(): void
    {
        foreach (['fa' => '/', 'en' => '/en', 'ar' => '/ar'] as $locale => $path) {
            $html = $this->get($path)->assertOk()->assertHeader('Vary', 'Accept-Language')->getContent();
            $this->assertStringContainsString('<html lang="'.$locale.'" dir="'.($locale === 'en' ? 'ltr' : 'rtl').'">', $html);
            foreach (['services', 'opg', 'home-dentistry', 'referrals', 'how', 'privacy', 'faq', 'contact'] as $key) {
                $this->assertStringContainsString('href="'.PublicUrl::to($key, $locale).'"', $html);
            }
            $this->assertStringContainsString('action="'.PublicUrl::to('services', $locale).'"', $html);
            $this->assertStringContainsString('id="need-q" name="q"', $html);
            $this->assertSame(3, substr_count($html, 'data-service-card'));
            $this->assertStringContainsString('href="'.route('login', ['locale' => $locale]).'"', $html);
            $this->assertStringContainsString(__('public_home.hero_title', [], $locale), $html);
            $this->assertStringNotContainsString('public_home.', $html);
        }
    }

    public function test_paused_intake_has_a_clear_notice_and_does_not_advertise_booking_or_round_the_clock_service(): void
    {
        config()->set('royadarman.intake_enabled', false);
        $html = $this->get('/en')->assertOk()->getContent();
        $this->assertStringContainsString('data-home-intake="paused"', $html);
        $this->assertStringContainsString(__('public_home.intake_paused', [], 'en'), $html);
        $this->assertStringContainsString(__('public_home.sign_in', [], 'en'), $html);
        foreach (['24/7', 'Round-the-clock', 'Book an appointment', 'Confirmed appointment'] as $claim) {
            $this->assertStringNotContainsString($claim, $html);
        }
        config()->set('royadarman.intake_enabled', true);
        $this->get('/en')->assertOk()->assertDontSee('data-home-intake="paused"', false)->assertSee(__('public_home.request_available', [], 'en'));
    }

    public function test_home_links_to_the_actual_discovery_surface_without_claiming_live_slot_availability(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();
        $this->assertStringContainsString('data-home-discovery-link', $html);
        $this->assertStringContainsString('href="#public-home-clinics"', $html);
        $this->assertStringContainsString('data-discovery-root', $html);
        $this->assertStringContainsString('data-endpoint="'.url('/api/v1/public/discovery/clinics').'"', $html);
        $this->assertStringContainsString(__('public_home.discovery_boundary', [], 'en'), $html);
        $this->get('/en/referrals')->assertOk()->assertSee('data-discovery-root', false)->assertSee('data-endpoint="'.url('/api/v1/public/discovery/clinics').'"', false)->assertSee('data-discovery-form', false)->assertSee('data-discovery-list', false);
    }

    public function test_a_real_eligible_clinic_renders_escaped_name_and_actual_navigation_bindings(): void
    {
        $clinic = Clinic::query()->create([
            'name' => '<svg onload="synthetic-clinic">Clinic</svg>', 'city' => 'Tehran', 'area_code' => 'north', 'is_active' => true,
            'latitude' => 35.7572, 'longitude' => 51.4103, 'location_recorded_at' => now(),
        ]);
        ClinicServiceCapability::query()->create([
            'clinic_id' => $clinic->id, 'service_type' => 'guidance_referral', 'suitability_status' => 'suitable', 'attested_at' => now(),
        ]);
        $html = $this->get('/en')->assertOk()->getContent();
        $this->assertStringContainsString('data-clinic-id="'.$clinic->id.'"', $html);
        $this->assertStringContainsString('&lt;svg onload=&quot;synthetic-clinic&quot;&gt;Clinic&lt;/svg&gt;', $html);
        $this->assertStringNotContainsString('<svg onload="synthetic-clinic">', $html);
        $this->assertStringContainsString('data-lat="35.7572" data-lng="51.4103"', $html);
        $this->assertStringContainsString('data-directions-link', $html);
        $this->assertStringContainsString('data-select-clinic aria-pressed="false"', $html);
        $this->assertStringContainsString(__('site.discovery.not_available_claim', [], 'en'), $html);
    }

    public function test_configured_neighbourhoods_remain_searchable_and_escaped(): void
    {
        config()->set('royadarman.tehran_neighborhoods', [['id' => 'synthetic-area', 'fa' => 'محله آزمایشی', 'en' => '<script>synthetic-area</script>', 'ar' => 'حي تجريبي', 'area' => 'SYNTHETIC', 'lat' => 35.75, 'lng' => 51.41]]);
        $html = $this->get('/en')->assertOk()->getContent();
        $this->assertStringContainsString('data-area="synthetic-area"', $html);
        $this->assertStringContainsString('&lt;script&gt;synthetic-area&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>synthetic-area</script>', $html);
        $this->assertStringContainsString(PublicUrl::to('services', 'en').'?q='.urlencode('<script>synthetic-area</script>'), $html);
    }

    public function test_home_has_no_executable_inline_scripts_styles_or_private_upload_form(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/\s(?:style|on[a-z]+)\s*=/i', $html);
        $this->assertStringNotContainsString('<style', $html);
        preg_match_all('/<script\b([^>]*)>/i', $html, $scripts);
        foreach ($scripts[1] as $attributes) {
            $this->assertTrue(str_contains($attributes, 'src=') || str_contains($attributes, 'type="application/ld+json"') || (str_contains($attributes, 'type="application/json"') && str_contains($attributes, 'data-discovery-copy')));
        }
        $this->assertStringContainsString('type="application/ld+json"', $html);
        $this->assertStringContainsString('"@type":"Organization"', $html);
        $this->assertStringNotContainsString('type="file"', $html);
        $this->assertFileExists(base_path('public/assets/public-home.css'));
        $this->assertSame(file_get_contents(base_path('public/assets/public-home.css')), file_get_contents(base_path('../deployment/webroot/assets/public-home.css')));
    }

    public function test_published_marketing_home_keeps_its_existing_precedence(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        MarketingPage::query()->create(['created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id, 'slug' => 'home', 'locale' => 'en', 'title' => 'Synthetic published home', 'excerpt' => 'Synthetic published content', 'body' => '<p>Published source retained</p>', 'status' => 'published', 'published_at' => now()]);
        $this->get('/en')->assertOk()->assertSee('Synthetic published home')->assertSee('Published source retained')->assertDontSee('class="public-home-brand-mark"', false);
    }
}
