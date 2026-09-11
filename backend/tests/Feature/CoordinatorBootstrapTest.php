<?php

namespace Tests\Feature;

use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoordinatorBootstrapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('royadarman.intake_enabled', true);
    }

    private function patient(): User
    {
        return User::factory()->create([
            'role' => 'patient',
            'locale' => 'fa',
            'phone' => '09121234567',
            'phone_hash' => hash('sha256', Str::random()),
            'is_active' => true,
        ]);
    }

    private function policy(): PolicyVersion
    {
        $content = 'case coordination consent';

        return PolicyVersion::query()->create([
            'policy_key' => 'case_coordination',
            'version' => '2026-09-11',
            'locale' => 'fa',
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'published_at' => now(),
        ]);
    }

    private function createDraft(User $patient): array
    {
        return $this->actingAs($patient)
            ->withHeaders(['X-Locale' => 'fa', 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/cases/draft', [
                'service_type' => 'referral',
                'name' => 'Patient',
                'preferred_contact_time' => 'any',
                'contact_reason' => 'Need coordination',
                'budget_band' => 'balanced',
                'budget_input_unit' => 'toman',
                'source_language' => 'fa',
            ])
            ->assertCreated()
            ->json('data');
    }

    public function test_submission_assigns_the_least_loaded_active_coordinator(): void
    {
        $patient = $this->patient();
        $policy = $this->policy();
        $busy = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);
        $available = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);

        $otherPatient = $this->patient();
        $existing = PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $otherPatient->id,
            'service_type' => 'referral',
            'status' => 'submitted',
            'patient_mobile' => '09120000000',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => 2,
        ]);
        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(),
            'case_id' => $existing->id,
            'assignee_user_id' => $busy->id,
            'assigned_by_user_id' => $busy->id,
            'purpose' => 'coordination',
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $draft = $this->createDraft($patient);

        $this->actingAs($patient)
            ->withHeaders(['X-Locale' => 'fa', 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/cases/{$draft['id']}/submit", [
                'version' => $draft['version'],
                'policy_version' => $policy->version,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('patient_cases', [
            'id' => $draft['id'],
            'current_coordinator_id' => $available->id,
            'status' => 'submitted',
        ]);
        $this->assertDatabaseHas('case_assignments', [
            'case_id' => $draft['id'],
            'assignee_user_id' => $available->id,
            'purpose' => 'coordination',
            'assigned_by_user_id' => null,
            'released_at' => null,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'resource_id' => $draft['id'],
            'action' => 'case.coordinator_auto_assigned',
            'result' => 'success',
        ]);
    }

    public function test_submission_rolls_back_when_no_active_coordinator_exists(): void
    {
        $patient = $this->patient();
        $policy = $this->policy();
        User::factory()->create(['role' => 'coordinator', 'is_active' => false]);
        $draft = $this->createDraft($patient);

        $this->actingAs($patient)
            ->withHeaders(['X-Locale' => 'fa', 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/cases/{$draft['id']}/submit", [
                'version' => $draft['version'],
                'policy_version' => $policy->version,
            ])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'coordination.no_active_coordinator');

        $this->assertDatabaseHas('patient_cases', [
            'id' => $draft['id'],
            'status' => 'draft',
            'current_coordinator_id' => null,
        ]);
        $this->assertDatabaseMissing('case_assignments', ['case_id' => $draft['id']]);
        $this->assertDatabaseMissing('consent_events', ['case_id' => $draft['id'], 'purpose' => 'case_coordination']);
        $this->assertDatabaseMissing('outbox_events', ['aggregate_id' => $draft['id'], 'event_type' => 'case.submitted']);
    }
}
