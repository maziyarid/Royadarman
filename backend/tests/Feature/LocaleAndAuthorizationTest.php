<?php

namespace Tests\Feature;

use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_public_locale_is_server_rendered_with_correct_direction(): void
    {
        $this->get('/')->assertOk()->assertSee('<html lang="fa" dir="rtl">', false);
        $this->get('/ar/')->assertOk()->assertSee('<html lang="ar" dir="rtl">', false);
        $this->get('/en/')->assertOk()->assertSee('<html lang="en" dir="ltr">', false);
    }

    public function test_persian_prefix_permanently_redirects_to_root(): void
    {
        $this->get('/fa/')->assertRedirect('/');
        $this->get('/fa/services/opg')->assertRedirect('/services/opg');
    }

    public function test_home_page_is_nonblank_and_has_skip_link_and_main_landmark(): void
    {
        $response = $this->get('/')->assertOk();
        $body = $response->getContent();
        $this->assertNotEmpty($body);
        $this->assertStringContainsString('class="skip-link"', $body);
        $this->assertStringContainsString('id="main"', $body);
    }

    public function test_home_page_declares_hreflang_alternates_for_all_three_locales(): void
    {
        $body = $this->get('/en/')->assertOk()->getContent();
        $this->assertStringContainsString('hreflang="fa"', $body);
        $this->assertStringContainsString('hreflang="ar"', $body);
        $this->assertStringContainsString('hreflang="en"', $body);
        $this->assertStringContainsString('hreflang="x-default"', $body);
    }

    public function test_locale_switching_links_present_and_point_to_each_locale(): void
    {
        $body = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('href="http://localhost"', $body);
        $this->assertStringContainsString('href="http://localhost/ar"', $body);
        $this->assertStringContainsString('href="http://localhost/en"', $body);
    }

    public function test_intake_disabled_state_is_honestly_represented_in_secure_login(): void
    {
        config()->set('royadarman.intake_enabled', false);
        $body = $this->get('/fa/login')->assertOk()->getContent();
        $this->assertStringContainsString('پذیرش بیمار جدید فعلا غیرفعال است', $body);
        $this->assertStringContainsString('id="challenge-form"', $body);
        $this->assertStringContainsString('id="verify-form"', $body);
    }

    public function test_faq_page_uses_progressive_enhancement_details_elements(): void
    {
        $body = $this->get('/faq')->assertOk()->getContent();
        $this->assertStringContainsString('<details>', $body);
        $this->assertStringContainsString('<summary>', $body);
        $this->assertStringContainsString('آیا رویا درمان یک کلینیک یا مطب است؟', $body);
    }

    public function test_assets_referenced_are_local_and_not_external_cdn(): void
    {
        $body = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('href="/assets/site.css', $body);
        $this->assertStringContainsString('src="/assets/site.js', $body);
        // Assets must be same-origin relative paths, never loaded from a CDN.
        $this->assertStringNotContainsString('src="https://cdn', $body);
        $this->assertStringNotContainsString('href="/https://', $body);
        $this->assertStringNotContainsString('unpkg.com', $body);
        $this->assertStringNotContainsString('cdnjs.cloudflare', $body);
    }

    public function test_public_information_architecture_is_multi_page(): void
    {
        foreach (['/services', '/services/opg', '/services/home-dentistry', '/referrals', '/how-it-works', '/about', '/contact', '/privacy', '/faq'] as $path) {
            $this->get($path)->assertOk();
        }
        foreach (['/ar/services', '/en/services', '/ar/about', '/en/about', '/ar/privacy', '/en/privacy'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_owner_has_no_implicit_patient_case_access(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient']);
        $owner = User::factory()->create(['role' => 'owner']);
        $case = PatientCase::query()->create(['public_reference' => 'RD-PRIVATE1', 'patient_user_id' => $patient->id, 'service_type' => 'guidance_referral', 'status' => 'submitted', 'patient_mobile' => '09121234567', 'patient_mobile_hash' => hash('sha256', 'private'), 'budget_band' => 'call']);
        $this->actingAs($owner)->getJson('/api/v1/cases/'.$case->id)->assertNotFound();
    }

    public function test_patient_cannot_enumerate_another_patients_case(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient']);
        $other = User::factory()->create(['role' => 'patient']);
        $case = PatientCase::query()->create(['public_reference' => 'RD-PRIVATE2', 'patient_user_id' => $patient->id, 'service_type' => 'guidance_referral', 'status' => 'submitted', 'patient_mobile' => '09121234567', 'patient_mobile_hash' => hash('sha256', 'other'), 'budget_band' => 'call']);
        $this->actingAs($other)->getJson('/api/v1/cases/'.$case->id)->assertNotFound();
    }

    public function test_validation_messages_are_localised_per_locale(): void
    {
        $payload = ['locale' => 'fa'];

        $fa = $this->postJson('/api/v1/auth/otp/challenge', $payload, ['X-Locale' => 'fa'])
            ->assertStatus(422)
            ->json('error.details.mobile.0');
        $this->assertStringContainsString('شماره همراه', $fa);

        $ar = $this->postJson('/api/v1/auth/otp/challenge', $payload, ['X-Locale' => 'ar'])
            ->assertStatus(422)
            ->json('error.details.mobile.0');
        $this->assertStringContainsString('رقم الهاتف', $ar);

        $en = $this->postJson('/api/v1/auth/otp/challenge', $payload, ['X-Locale' => 'en'])
            ->assertStatus(422)
            ->json('error.details.mobile.0');
        $this->assertStringContainsString('mobile number', $en);
    }
}
