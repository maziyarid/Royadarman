<?php

namespace Tests\Feature;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Jobs\ProcessOutboxEvent;
use App\Models\ClinicalDocument;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReferralProposal;
use App\Models\ReviewRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('royadarman.intake_enabled', true);
    }

    private function makeCase(User $patient, CaseStatus $status = CaseStatus::InCoordination): PatientCase
    {
        return PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'opg_review',
            'status' => $status,
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => 1,
        ]);
    }

    private function makePractitioner(User $clinician, string $status = 'verified', ?\DateTimeInterface $expires = null): void
    {
        DB::table('practitioners')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $clinician->id,
            'licence_number' => encrypt('LIC-'.$clinician->id),
            'licence_hash' => hash('sha256', 'LIC-'.$clinician->id),
            'credential_status' => $status,
            'verified_at' => $status === 'verified' ? now()->subDay() : null,
            'expires_at' => $expires?->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignCoordinator(User $coordinator, PatientCase $case): void
    {
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
    }

    // ---------- Assignments ----------

    public function test_only_coordinator_can_assign(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $case = $this->makeCase($patient);
        $this->actingAs($clinician)
            ->postJson("/api/v1/staff/cases/{$case->id}/assignments", [
                'assignee_user_id' => $clinician->id,
                'purpose' => 'coordination',
                'version' => 1,
            ])
            ->assertNotFound();
    }

    public function test_technical_administrator_cannot_assign_clinical_review(): void
    {
        $techAdmin = User::factory()->create(['role' => 'tech_admin']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $case = $this->makeCase($patient);
        $this->actingAs($techAdmin)
            ->postJson("/api/v1/staff/cases/{$case->id}/assignments", [
                'assignee_user_id' => $clinician->id,
                'purpose' => 'clinical_review',
                'version' => 1,
            ])
            ->assertNotFound();
    }

    public function test_clinical_review_requires_clinician_role(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $anotherPatient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $this->assignCoordinator($coordinator, $case);

        $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$case->id}/assignments", [
                'assignee_user_id' => $anotherPatient->id,
                'purpose' => 'clinical_review',
                'version' => 1,
            ])
            ->assertForbidden();
    }

    public function test_clinical_review_requires_verified_non_expired_practitioner(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $unverified = User::factory()->create(['role' => 'clinician']);
        $expired = User::factory()->create(['role' => 'clinician']);
        $valid = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($unverified, 'pending');
        $this->makePractitioner($expired, 'verified', now()->subSecond());
        $this->makePractitioner($valid, 'verified', now()->addYear());

        $caseUnverified = $this->makeCase($patient);
        $this->assignCoordinator($coordinator, $caseUnverified);
        $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$caseUnverified->id}/assignments", [
                'assignee_user_id' => $unverified->id,
                'purpose' => 'clinical_review',
                'version' => 1,
            ])
            ->assertForbidden();

        $caseExpired = $this->makeCase($patient);
        $this->assignCoordinator($coordinator, $caseExpired);
        $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$caseExpired->id}/assignments", [
                'assignee_user_id' => $expired->id,
                'purpose' => 'clinical_review',
                'version' => 1,
            ])
            ->assertForbidden();

        $caseValid = $this->makeCase($patient);
        $this->assignCoordinator($coordinator, $caseValid);
        $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$caseValid->id}/assignments", [
                'assignee_user_id' => $valid->id,
                'purpose' => 'clinical_review',
                'version' => 1,
            ])
            ->assertOk();
        $this->assertDatabaseHas('case_assignments', ['case_id' => $caseValid->id, 'assignee_user_id' => $valid->id, 'purpose' => 'clinical_review']);
    }

    public function test_reassigning_same_clinician_is_idempotent_and_does_not_duplicate(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($clinician, 'verified', now()->addYear());
        $case = $this->makeCase($patient);
        $this->assignCoordinator($coordinator, $case);

        $first = $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$case->id}/assignments", [
                'assignee_user_id' => $clinician->id,
                'purpose' => 'clinical_review',
                'version' => 1,
            ])
            ->assertOk();
        $second = $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$case->id}/assignments", [
                'assignee_user_id' => $clinician->id,
                'purpose' => 'clinical_review',
                'version' => $first->json('data.version'),
            ])
            ->assertOk();

        $this->assertSame(1, DB::table('case_assignments')->where(['case_id' => $case->id, 'assignee_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'released_at' => null])->count());
    }

    public function test_unassigned_coordinator_cannot_assign(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($clinician, 'verified', now()->addYear());
        $case = $this->makeCase($patient);

        $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$case->id}/assignments", [
                'assignee_user_id' => $clinician->id,
                'purpose' => 'clinical_review',
                'version' => 1,
            ])
            ->assertNotFound();
    }

    public function test_assignment_version_conflict(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($clinician, 'verified', now()->addYear());
        $case = $this->makeCase($patient);
        $this->assignCoordinator($coordinator, $case);

        $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$case->id}/assignments", [
                'assignee_user_id' => $clinician->id,
                'purpose' => 'clinical_review',
                'version' => 99,
            ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'case.version_conflict');
    }

    // ---------- Case state changes ----------

    public function test_unassigned_coordinator_cannot_change_status(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient, CaseStatus::Submitted);

        $this->actingAs($coordinator)
            ->patchJson("/api/v1/staff/cases/{$case->id}/status", [
                'status' => 'awaiting_contact',
                'version' => 1,
            ])
            ->assertNotFound();
    }

    public function test_assigned_coordinator_can_transition(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient, CaseStatus::Submitted);
        $this->assignCoordinator($coordinator, $case);

        $this->actingAs($coordinator)
            ->patchJson("/api/v1/staff/cases/{$case->id}/status", [
                'status' => 'awaiting_contact',
                'version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_contact');
        $this->assertDatabaseHas('case_status_events', ['case_id' => $case->id, 'from_status' => 'submitted', 'to_status' => 'awaiting_contact']);
    }

    public function test_invalid_transition_rejected(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient, CaseStatus::Resolved);
        $this->assignCoordinator($coordinator, $case);

        $this->actingAs($coordinator)
            ->patchJson("/api/v1/staff/cases/{$case->id}/status", [
                'status' => 'submitted',
                'version' => 1,
            ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'case.invalid_transition');
    }

    public function test_status_version_conflict(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient, CaseStatus::Submitted);
        $this->assignCoordinator($coordinator, $case);

        $this->actingAs($coordinator)
            ->patchJson("/api/v1/staff/cases/{$case->id}/status", [
                'status' => 'awaiting_contact',
                'version' => 99,
            ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'case.version_conflict');
    }

    public function test_owner_cannot_change_status(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);

        $this->actingAs($owner)
            ->patchJson("/api/v1/staff/cases/{$case->id}/status", [
                'status' => 'awaiting_contact',
                'version' => 1,
            ])
            ->assertNotFound();
    }

    // ---------- Referrals ----------

    private function makeClinic(): string
    {
        $id = (string) Str::ulid();
        DB::table('clinics')->insert(['id' => $id, 'name' => 'Partner Clinic', 'city' => 'Tehran', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public function test_only_assigned_coordinator_can_propose_referral(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $unassigned = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinicId = $this->makeClinic();
        $case = $this->makeCase($patient, CaseStatus::InCoordination);
        $this->assignCoordinator($coordinator, $case);

        $this->actingAs($unassigned)
            ->postJson("/api/v1/staff/cases/{$case->id}/referral-proposals", [
                'clinic_id' => $clinicId,
                'reasoning' => 'needs specialist',
                'source_language' => 'fa',
            ])
            ->assertNotFound();

        $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$case->id}/referral-proposals", [
                'clinic_id' => $clinicId,
                'reasoning' => 'needs specialist',
                'source_language' => 'fa',
            ])
            ->assertCreated();
    }

    public function test_patient_can_accept_referral_and_grant_links_to_consent(): void
    {
        Queue::fake([ProcessOutboxEvent::class]);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinicId = $this->makeClinic();
        PolicyVersion::query()->create([
            'policy_key' => 'referral_sharing',
            'version' => 'approved-1',
            'locale' => 'fa',
            'content' => 'referral sharing text',
            'content_hash' => hash('sha256', 'referral sharing text'),
            'published_at' => now(),
        ]);

        $case = $this->makeCase($patient, CaseStatus::ReferralProposed);
        $this->assignCoordinator($coordinator, $case);
        $proposal = ReferralProposal::query()->create([
            'case_id' => $case->id,
            'clinic_id' => $clinicId,
            'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed',
            'reasoning' => encrypt('needs specialist'),
            'source_language' => 'fa',
            'proposed_at' => now(),
        ]);

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/referrals/{$proposal->id}/decision", [
                'decision' => 'accepted',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('referral_grants', ['proposal_id' => $proposal->id, 'case_id' => $case->id]);
        $this->assertDatabaseHas('consent_events', ['subject_user_id' => $patient->id, 'purpose' => 'referral_sharing', 'case_id' => $case->id, 'decision' => 'accepted']);
        $grant = DB::table('referral_grants')->where('proposal_id', $proposal->id)->first();
        $consent = DB::table('consent_events')->where('subject_user_id', $patient->id)->where('purpose', 'referral_sharing')->first();
        $this->assertSame($consent->id, $grant->consent_event_id);
    }

    public function test_patient_can_decline_referral_without_grant(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinicId = $this->makeClinic();
        $case = $this->makeCase($patient, CaseStatus::ReferralProposed);
        $this->assignCoordinator($coordinator, $case);
        $proposal = ReferralProposal::query()->create([
            'case_id' => $case->id,
            'clinic_id' => $clinicId,
            'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed',
            'reasoning' => encrypt('needs specialist'),
            'source_language' => 'fa',
            'proposed_at' => now(),
        ]);

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/referrals/{$proposal->id}/decision", [
                'decision' => 'declined',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');

        $this->assertDatabaseMissing('referral_grants', ['proposal_id' => $proposal->id]);
        $this->assertDatabaseMissing('consent_events', ['purpose' => 'referral_sharing', 'case_id' => $case->id]);
    }

    public function test_duplicate_patient_decision_rejected(): void
    {
        Queue::fake([ProcessOutboxEvent::class]);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinicId = $this->makeClinic();
        PolicyVersion::query()->create([
            'policy_key' => 'referral_sharing',
            'version' => 'approved-1',
            'locale' => 'fa',
            'content' => 'referral sharing text',
            'content_hash' => hash('sha256', 'referral sharing text'),
            'published_at' => now(),
        ]);
        $case = $this->makeCase($patient, CaseStatus::ReferralProposed);
        $this->assignCoordinator($coordinator, $case);
        $proposal = ReferralProposal::query()->create([
            'case_id' => $case->id,
            'clinic_id' => $clinicId,
            'proposed_by_user_id' => $coordinator->id,
            'status' => 'accepted',
            'reasoning' => encrypt('needs specialist'),
            'source_language' => 'fa',
            'proposed_at' => now(),
            'decided_at' => now(),
        ]);

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/referrals/{$proposal->id}/decision", [
                'decision' => 'accepted',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'referral.not_available');
    }

    // ---------- Clinical reviews ----------

    private function approvedDocument(PatientCase $case): ClinicalDocument
    {
        return ClinicalDocument::query()->create([
            'case_id' => $case->id,
            'uploaded_by_user_id' => $case->patient_user_id,
            'original_name' => encrypt('opg.png'),
            'storage_disk' => 'private-opg',
            'storage_key' => 'cases/'.$case->id.'/'.Str::ulid().'.png',
            'detected_mime' => 'image/png',
            'byte_size' => 68,
            'sha256' => hash('sha256', 'image-bytes'),
            'status' => DocumentStatus::Approved,
        ]);
    }

    public function test_only_assigned_clinician_can_create_review(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $assigned = User::factory()->create(['role' => 'clinician']);
        $other = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($assigned, 'verified', now()->addYear());
        $this->makePractitioner($other, 'verified', now()->addYear());
        $case = $this->makeCase($patient, CaseStatus::ClinicianReview);
        $doc = $this->approvedDocument($case);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $assigned->id, 'assigned_by_user_id' => $assigned->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($other)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews", [
                'source_language' => 'fa',
                'clinical_document_id' => $doc->id,
                'image_adequacy' => 'adequate',
                'observations' => 'observations',
                'limitations' => 'limitations',
                'options' => 'options',
                'recommended_next_step' => 'next step',
            ])
            ->assertNotFound();

        $this->actingAs($assigned)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews", [
                'source_language' => 'fa',
                'clinical_document_id' => $doc->id,
                'image_adequacy' => 'adequate',
                'observations' => 'observations',
                'limitations' => 'limitations',
                'options' => 'options',
                'recommended_next_step' => 'next step',
            ])
            ->assertCreated();
    }

    public function test_review_cannot_reference_non_approved_document(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($clinician, 'verified', now()->addYear());
        $case = $this->makeCase($patient, CaseStatus::ClinicianReview);
        $quarantined = ClinicalDocument::query()->create([
            'case_id' => $case->id,
            'uploaded_by_user_id' => $patient->id,
            'original_name' => encrypt('opg.png'),
            'storage_disk' => 'opg-quarantine',
            'storage_key' => 'cases/'.$case->id.'/'.Str::ulid().'.png',
            'detected_mime' => 'image/png',
            'byte_size' => 68,
            'sha256' => hash('sha256', 'image-bytes'),
            'status' => DocumentStatus::Quarantined,
        ]);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $clinician->id, 'assigned_by_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($clinician)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews", [
                'source_language' => 'fa',
                'clinical_document_id' => $quarantined->id,
                'image_adequacy' => 'adequate',
                'observations' => 'observations',
                'limitations' => 'limitations',
                'options' => 'options',
                'recommended_next_step' => 'next step',
            ])
            ->assertStatus(422);
    }

    public function test_review_cannot_reference_document_from_another_case(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($clinician, 'verified', now()->addYear());
        $case = $this->makeCase($patient, CaseStatus::ClinicianReview);
        $otherCase = $this->makeCase(User::factory()->create(['role' => 'patient']), CaseStatus::ClinicianReview);
        $docFromOtherCase = ClinicalDocument::query()->create([
            'case_id' => $otherCase->id,
            'uploaded_by_user_id' => $otherCase->patient_user_id,
            'original_name' => encrypt('opg.png'),
            'storage_disk' => 'private-opg',
            'storage_key' => 'cases/'.$otherCase->id.'/'.Str::ulid().'.png',
            'detected_mime' => 'image/png',
            'byte_size' => 68,
            'sha256' => hash('sha256', 'image-bytes'),
            'status' => DocumentStatus::Approved,
        ]);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $clinician->id, 'assigned_by_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($clinician)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews", [
                'source_language' => 'fa',
                'clinical_document_id' => $docFromOtherCase->id,
                'image_adequacy' => 'adequate',
                'observations' => 'observations',
                'limitations' => 'limitations',
                'options' => 'options',
                'recommended_next_step' => 'next step',
            ])
            ->assertStatus(422);
    }

    public function test_unverified_clinician_cannot_create_review(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($clinician, 'pending');
        $case = $this->makeCase($patient, CaseStatus::ClinicianReview);
        $doc = $this->approvedDocument($case);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $clinician->id, 'assigned_by_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($clinician)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews", [
                'source_language' => 'fa',
                'clinical_document_id' => $doc->id,
                'image_adequacy' => 'adequate',
                'observations' => 'observations',
                'limitations' => 'limitations',
                'options' => 'options',
                'recommended_next_step' => 'next step',
            ])
            ->assertNotFound();
    }

    public function test_only_author_can_publish_review(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $author = User::factory()->create(['role' => 'clinician']);
        $other = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($author, 'verified', now()->addYear());
        $this->makePractitioner($other, 'verified', now()->addYear());
        $case = $this->makeCase($patient, CaseStatus::ClinicianReview);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $author->id, 'assigned_by_user_id' => $author->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $revision = ReviewRevision::query()->create([
            'case_id' => $case->id,
            'clinician_user_id' => $author->id,
            'revision_number' => 1,
            'source_language' => 'fa',
            'image_adequacy' => 'adequate',
            'observations' => 'observations',
            'limitations' => 'limitations',
            'options' => 'options',
            'recommended_next_step' => 'next step',
        ]);

        $this->actingAs($other)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews/{$revision->id}/publish")
            ->assertNotFound();

        $this->actingAs($author)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews/{$revision->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.published', true);
    }

    public function test_duplicate_publish_returns_conflict(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $author = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($author, 'verified', now()->addYear());
        $case = $this->makeCase($patient, CaseStatus::ClinicianReview);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $author->id, 'assigned_by_user_id' => $author->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $revision = ReviewRevision::query()->create([
            'case_id' => $case->id,
            'clinician_user_id' => $author->id,
            'revision_number' => 1,
            'source_language' => 'fa',
            'image_adequacy' => 'adequate',
            'observations' => 'observations',
            'limitations' => 'limitations',
            'options' => 'options',
            'recommended_next_step' => 'next step',
            'signed_at' => now(),
        ]);

        $this->actingAs($author)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews/{$revision->id}/publish")
            ->assertConflict()
            ->assertJsonPath('error.code', 'review.already_published');
        $this->assertDatabaseCount('publication_events', 0);
    }

    public function test_publish_after_credential_revoked_rejected(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $author = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($author, 'verified', now()->addYear());
        $case = $this->makeCase($patient, CaseStatus::ClinicianReview);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $author->id, 'assigned_by_user_id' => $author->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $revision = ReviewRevision::query()->create([
            'case_id' => $case->id,
            'clinician_user_id' => $author->id,
            'revision_number' => 1,
            'source_language' => 'fa',
            'image_adequacy' => 'adequate',
            'observations' => 'observations',
            'limitations' => 'limitations',
            'options' => 'options',
            'recommended_next_step' => 'next step',
        ]);

        DB::table('practitioners')->where('user_id', $author->id)->update(['credential_status' => 'revoked']);

        $this->actingAs($author)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews/{$revision->id}/publish")
            ->assertNotFound();
    }

    public function test_owner_and_tech_admin_cannot_publish(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $author = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($author, 'verified', now()->addYear());
        $case = $this->makeCase($patient, CaseStatus::ClinicianReview);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $author->id, 'assigned_by_user_id' => $author->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $revision = ReviewRevision::query()->create([
            'case_id' => $case->id,
            'clinician_user_id' => $author->id,
            'revision_number' => 1,
            'source_language' => 'fa',
            'image_adequacy' => 'adequate',
            'observations' => 'observations',
            'limitations' => 'limitations',
            'options' => 'options',
            'recommended_next_step' => 'next step',
        ]);

        $owner = User::factory()->create(['role' => 'owner']);
        $techAdmin = User::factory()->create(['role' => 'tech_admin']);
        $this->actingAs($owner)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews/{$revision->id}/publish")
            ->assertNotFound();
        $this->actingAs($techAdmin)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews/{$revision->id}/publish")
            ->assertNotFound();
    }

    public function test_review_revision_numbers_are_sequential(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($clinician, 'verified', now()->addYear());
        $case = $this->makeCase($patient, CaseStatus::ClinicianReview);
        $doc = $this->approvedDocument($case);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $clinician->id, 'assigned_by_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $first = $this->actingAs($clinician)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews", [
                'source_language' => 'fa',
                'clinical_document_id' => $doc->id,
                'image_adequacy' => 'v1', 'observations' => 'o', 'limitations' => 'l', 'options' => 'op', 'recommended_next_step' => 's',
            ])->assertCreated()->json('data.revision_number');

        $second = $this->actingAs($clinician)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews", [
                'source_language' => 'fa',
                'clinical_document_id' => $doc->id,
                'image_adequacy' => 'v2', 'observations' => 'o', 'limitations' => 'l', 'options' => 'op', 'recommended_next_step' => 's',
            ])->assertCreated()->json('data.revision_number');

        $this->assertSame(1, $first);
        $this->assertSame(2, $second);
    }
}
