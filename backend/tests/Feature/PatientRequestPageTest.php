<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientRequestPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_review_new_request_page_but_cannot_submit_when_intake_disabled(): void
    {
        config()->set('royadarman.intake_enabled', false);
        $patient = User::factory()->create(['role' => 'patient', 'is_active' => true]);

        $this->actingAs($patient)
            ->get('/en/panel/cases/new')
            ->assertOk()
            ->assertSee('Start a new care request')
            ->assertSee('New requests are not open yet')
            ->assertSee('id="request-form"', false)
            ->assertSee('data-intake="0"', false)
            ->assertSee('data-step="8"', false);

        $script = file_get_contents(public_path('assets/patient-request.js'));
        $this->assertIsString($script);
        $this->assertStringContainsString("dataset.intake === '1'", $script);
        $this->assertStringContainsString('/api/v1/cases/draft', $script);
    }

    public function test_non_patient_cannot_open_patient_new_request_page(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);

        $this->actingAs($coordinator)
            ->get('/en/panel/cases/new')
            ->assertNotFound();
    }

    public function test_enabled_request_page_contains_guided_fail_closed_workflow(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient', 'is_active' => true]);

        $response = $this->actingAs($patient)
            ->get('/en/panel/cases/new')
            ->assertOk()
            ->assertSee('id="request-form"', false)
            ->assertSee('/assets/patient-request.js', false)
            ->assertSee('data-patient-request', false)
            ->assertSee('data-intake="1"', false)
            ->assertSee('data-step="1"', false)
            ->assertSee('data-step="8"', false)
            ->assertSee('guidance_referral', false)
            ->assertSee('name="priority"', false)
            ->assertSee('data-neighborhood', false);

        $script = file_get_contents(public_path('assets/patient-request.js'));
        $this->assertIsString($script);
        $this->assertStringContainsString('/api/v1/policies/case_coordination', $script);
        $this->assertStringContainsString('Idempotency-Key', $script);
        $this->assertStringContainsString('content_hash: policy.content_hash', $script);
        $this->assertStringContainsString('priority: selectedPriority()', $script);
    }
}
