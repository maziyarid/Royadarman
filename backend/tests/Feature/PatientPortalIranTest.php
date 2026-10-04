<?php

namespace Tests\Feature;

use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PatientPortalIranTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): User
    {
        Queue::fake();
        return User::factory()->create(['role' => 'patient', 'locale' => 'fa', 'is_active' => true, 'phone' => '09121234567', 'phone_hash' => hash('sha256', 'synthetic-portal')]);
    }

    public function test_patient_lands_on_actionable_private_portal_and_nationwide_request_form(): void
    {
        config(['royadarman.intake_enabled' => true]);
        $this->actingAs($this->patient())->get('/fa/panel')->assertOk()
            ->assertSee('data-patient-portal', false)->assertSee('پرونده و پروفایل من')
            ->assertSee('تصاویر و مدارک OPG')->assertSee('درخواست‌ها و پیگیری')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/fa/panel/cases/new')->assertOk()->assertSee('name="province"', false)
            ->assertSee('name="city"', false)->assertSee('البرز')->assertSee('فارس')->assertSee('سیستان');
    }

    public function test_profile_persists_only_own_fields_and_encrypts_contact_details(): void
    {
        $patient = $this->patient();
        $other = User::factory()->create(['name' => 'Other patient']);
        $this->actingAs($patient)->post('/fa/panel/patient-profile', [
            'name' => 'Synthetic Patient', 'province' => 'fars', 'city' => 'شیراز',
            'neighborhood' => 'معالی آباد', 'address' => 'Synthetic private address',
            'contact_email' => 'synthetic@example.test', 'preferred_contact_time' => 'evening',
            'version' => 0, 'user_id' => $other->id, 'role' => 'owner',
        ])->assertRedirect('/fa/panel/patient-profile');
        $this->assertDatabaseHas('patient_contact_profiles', ['user_id' => $patient->id, 'province' => 'fars', 'city' => 'شیراز', 'version' => 1]);
        $this->assertDatabaseMissing('patient_contact_profiles', ['address' => 'Synthetic private address']);
        $this->assertDatabaseMissing('patient_contact_profiles', ['contact_email' => 'synthetic@example.test']);
        $this->assertDatabaseMissing('patient_contact_profiles', ['user_id' => $other->id]);
        $this->assertSame('patient', $patient->fresh()->role->value);
        $this->get('/fa/panel/patient-profile')->assertOk()->assertSee('Synthetic private address')->assertDontSee('Other patient');
        $this->assertStringNotContainsString('Synthetic private address', json_encode(DB::table('audit_events')->get()));
    }

    public function test_profile_rejects_invalid_province_unconsented_gps_and_stale_update(): void
    {
        $this->actingAs($this->patient());
        $this->postJson('/fa/panel/patient-profile', ['name' => 'Patient', 'province' => 'not-iran', 'city' => 'Place', 'version' => 0])->assertUnprocessable();
        $payload = ['name' => 'Patient', 'province' => 'tehran', 'city' => 'تهران', 'version' => 0];
        $this->postJson('/fa/panel/patient-profile', $payload + ['latitude' => 35.7, 'longitude' => 51.4])->assertUnprocessable();
        $this->post('/fa/panel/patient-profile', $payload)->assertRedirect();
        $this->postJson('/fa/panel/patient-profile', $payload)->assertConflict();
    }

    public function test_non_patient_and_guest_cannot_access_patient_profile(): void
    {
        $this->get('/fa/panel/patient-profile')->assertRedirect('/fa/login');
        $this->actingAs(User::factory()->create(['role' => 'owner', 'is_active' => true]))->get('/fa/panel/patient-profile')->assertNotFound();
        $this->post('/fa/panel/patient-profile', [])->assertNotFound();
    }

    public function test_case_location_is_persisted_idempotently_without_tehran_requirement(): void
    {
        config(['royadarman.intake_enabled' => true]);
        $this->actingAs($this->patient());
        $payload = ['service_type' => 'home_dentistry', 'budget_band' => 'call', 'budget_input_unit' => 'toman', 'source_language' => 'fa', 'province' => 'fars', 'city' => 'شیراز', 'neighborhood' => 'معالی آباد', 'address' => 'Private synthetic place'];
        $first = $this->postJson('/api/v1/cases/draft', $payload, ['Idempotency-Key' => 'iran-case-1'])->assertCreated();
        $caseId = $first->json('data.id');
        $this->postJson('/api/v1/cases/draft', $payload, ['Idempotency-Key' => 'iran-case-1'])->assertCreated()->assertJsonPath('data.id', $caseId);
        $this->assertDatabaseCount('case_locations', 1);
        $this->assertDatabaseHas('case_locations', ['case_id' => $caseId, 'province' => 'fars', 'city' => 'شیراز']);
        $this->assertDatabaseMissing('case_locations', ['address' => 'Private synthetic place']);
        $this->get('/fa/panel/cases/'.$caseId)->assertOk()->assertSee('شیراز');
        $this->actingAs(User::factory()->create(['role' => 'patient']))->get('/fa/panel/cases/'.$caseId)->assertNotFound();
    }

    public function test_nationwide_request_rejects_stale_tehran_area_for_other_province(): void
    {
        config(['royadarman.intake_enabled' => true]);
        $payload = ['service_type' => 'guidance_referral', 'budget_band' => 'call', 'budget_input_unit' => 'toman', 'source_language' => 'fa', 'province' => 'fars', 'city' => 'شیراز', 'tehran_area' => config('royadarman.tehran_areas')[0]];
        $this->actingAs($this->patient())->postJson('/api/v1/cases/draft', $payload, ['Idempotency-Key' => 'stale-area'])->assertUnprocessable();
    }
}
