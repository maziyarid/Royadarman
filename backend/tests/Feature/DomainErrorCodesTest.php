<?php

namespace Tests\Feature;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\ClinicalDocument;
use App\Models\PatientCase;
use App\Models\User;
use App\Support\DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainErrorCodesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('royadarman.intake_enabled', true);
        config()->set('royadarman.referral.grant_ttl_minutes', 43200);
    }

    private function makeCase(User $patient): PatientCase
    {
        return PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'opg_review',
            'status' => CaseStatus::InCoordination,
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => 1,
        ]);
    }

    private function makePractitioner(User $clinician, string $status = 'verified'): void
    {
        DB::table('practitioners')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $clinician->id,
            'licence_number' => encrypt('LIC-'.$clinician->id),
            'licence_hash' => hash('sha256', 'LIC-'.$clinician->id),
            'credential_status' => $status,
            'verified_at' => $status === 'verified' ? now()->subDay() : null,
            'expires_at' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignCoordinator(User $coordinator, PatientCase $case): void
    {
        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $coordinator->id,
            'assigned_by_user_id' => $coordinator->id, 'purpose' => 'coordination', 'assigned_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_assignment_role_mismatch_returns_stable_domain_code(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $nonClinician = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $this->assignCoordinator($coordinator, $case);

        $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$case->id}/assignments", [
                'assignee_user_id' => $nonClinician->id,
                'purpose' => 'clinical_review',
                'version' => 1,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'assignment.role_mismatch')
            ->assertJsonStructure(['error' => ['code', 'message'], 'request_id']);
    }

    public function test_assignment_credential_invalid_returns_stable_domain_code(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($clinician, 'pending');
        $case = $this->makeCase($patient);
        $this->assignCoordinator($coordinator, $case);

        $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$case->id}/assignments", [
                'assignee_user_id' => $clinician->id,
                'purpose' => 'clinical_review',
                'version' => 1,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'assignment.credential_invalid')
            ->assertJsonStructure(['error' => ['code', 'message'], 'request_id']);
    }

    public function test_review_document_not_approved_returns_stable_domain_code(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($clinician);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-X', 'patient_user_id' => $patient->id, 'service_type' => 'opg_review',
            'status' => CaseStatus::ClinicianReview, 'patient_mobile' => '09121234567', 'patient_mobile_hash' => hash('sha256', 'x'),
            'budget_band' => 'balanced', 'source_language' => 'fa', 'version' => 1,
        ]);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $clinician->id, 'assigned_by_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $quarantined = ClinicalDocument::query()->create([
            'case_id' => $case->id, 'uploaded_by_user_id' => $patient->id,
            'original_name' => encrypt('opg.png'), 'storage_disk' => 'opg-quarantine', 'storage_key' => 'k/'.Str::ulid().'.png',
            'detected_mime' => 'image/png', 'byte_size' => 68, 'sha256' => hash('sha256', 'x'), 'status' => DocumentStatus::Quarantined,
        ]);

        $this->actingAs($clinician)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews", [
                'source_language' => 'fa', 'clinical_document_id' => $quarantined->id,
                'image_adequacy' => 'a', 'observations' => 'o', 'limitations' => 'l', 'options' => 'op', 'recommended_next_step' => 's',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'review.document_not_approved')
            ->assertJsonStructure(['error' => ['code', 'message'], 'request_id']);
    }

    public function test_review_credential_revoked_renders_stable_domain_code(): void
    {
        // The in-transaction credential recheck is defence-in-depth: the publish
        // guard already denies via 404 when credentials are revoked, but if a future
        // policy change makes the guard more permissive the in-transaction recheck
        // must still surface the stable code. Verify the DomainException path directly.
        $this->app['router']->post('/debug-credential-revoked', fn () => throw new DomainException(403, 'review.credential_revoked'))
            ->middleware('web');

        $this->postJson('/debug-credential-revoked')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'review.credential_revoked')
            ->assertJsonStructure(['error' => ['code', 'message'], 'request_id']);
    }

    public function test_review_assignment_released_renders_stable_domain_code(): void
    {
        $this->app['router']->post('/debug-assignment-released', fn () => throw new DomainException(403, 'review.assignment_released'))
            ->middleware('web');

        $this->postJson('/debug-assignment-released')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'review.assignment_released')
            ->assertJsonStructure(['error' => ['code', 'message'], 'request_id']);
    }

    public function test_domain_error_envelope_includes_request_id(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $nonClinician = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $this->assignCoordinator($coordinator, $case);

        $response = $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$case->id}/assignments", [
                'assignee_user_id' => $nonClinician->id,
                'purpose' => 'clinical_review',
                'version' => 1,
            ])
            ->assertForbidden();

        $this->assertNotNull($response->json('request_id'));
        $this->assertNotSame('', $response->json('request_id'));
    }
}
