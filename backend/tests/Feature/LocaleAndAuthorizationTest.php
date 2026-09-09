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
        $this->get('/fa/')->assertOk()->assertSee('<html lang="fa" dir="rtl">', false);
        $this->get('/ar/')->assertOk()->assertSee('<html lang="ar" dir="rtl">', false);
        $this->get('/en/')->assertOk()->assertSee('<html lang="en" dir="ltr">', false);
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
