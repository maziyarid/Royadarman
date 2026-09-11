<?php

namespace Tests\Feature;

use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class IntakeGateScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_intake_blocks_new_acquisition_but_not_existing_patient_case_view(): void
    {
        config()->set('royadarman.intake_enabled', false);

        $patient = User::factory()->create([
            'role' => 'patient',
            'is_active' => true,
            'phone' => '09121234567',
            'phone_hash' => hash('sha256', 'patient-intake-gate'),
        ]);
        $case = $this->makeCase($patient, 'submitted', 2);

        $this->actingAs($patient)
            ->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $case->id)
            ->assertJsonPath('data.status', 'submitted');

        $this->actingAs($patient)
            ->postJson('/api/v1/cases/draft', [], ['Idempotency-Key' => 'disabled-draft'])
            ->assertServiceUnavailable();

        $draft = $this->makeCase($patient, 'draft', 1);
        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$draft->id}/submit", [], ['Idempotency-Key' => 'disabled-submit'])
            ->assertServiceUnavailable();
    }

    public function test_disabled_intake_does_not_block_assigned_coordinator_work_on_existing_case(): void
    {
        config()->set('royadarman.intake_enabled', false);

        $patient = User::factory()->create([
            'role' => 'patient',
            'is_active' => true,
            'phone' => '09121234567',
            'phone_hash' => hash('sha256', 'patient-existing-work'),
        ]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);
        $case = $this->makeCase($patient, 'submitted', 2);

        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(),
            'case_id' => $case->id,
            'assignee_user_id' => $coordinator->id,
            'assigned_by_user_id' => $coordinator->id,
            'purpose' => 'coordination',
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($coordinator)
            ->patchJson("/api/v1/staff/cases/{$case->id}/status", [
                'status' => 'awaiting_contact',
                'version' => 2,
                'reason' => 'Continue work while new intake is paused.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_contact');
    }

    private function makeCase(User $patient, string $status, int $version): PatientCase
    {
        return PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'guidance_referral',
            'status' => $status,
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => $patient->phone_hash,
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => $version,
        ]);
    }
}
