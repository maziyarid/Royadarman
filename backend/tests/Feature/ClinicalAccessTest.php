<?php

namespace Tests\Feature;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\ClinicalDocument;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\PublicationEvent;
use App\Models\ReviewRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClinicalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_clinician_credential_revokes_existing_assignment_immediately(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $case = PatientCase::query()->create(['public_reference' => 'RD-EXPIRED1', 'patient_user_id' => $patient->id, 'service_type' => 'opg_review', 'status' => 'clinician_review', 'patient_mobile' => '09121234567', 'patient_mobile_hash' => hash('sha256', 'clinical'), 'budget_band' => 'balanced']);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $clinician->id, 'assigned_by_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('practitioners')->insert(['id' => (string) Str::ulid(), 'user_id' => $clinician->id, 'licence_number' => encrypt('LIC-1'), 'licence_hash' => hash('sha256', 'LIC-1'), 'credential_status' => 'verified', 'verified_at' => now()->subDay(), 'expires_at' => now()->subSecond(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($clinician)->getJson('/api/v1/cases/'.$case->id)->assertNotFound();
    }

    public function test_assigned_licensed_clinician_can_create_and_publish_review(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $case = $this->clinicalCase($patient, $clinician);
        $document = $this->approvedDocument($case);

        $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews', [
            'source_language' => 'fa',
            'clinical_document_id' => $document->id,
            'image_adequacy' => 'Adequate',
            'observations' => 'Observation text',
            'limitations' => 'Limitation text',
            'options' => 'Options text',
            'recommended_next_step' => 'Next step',
            'budget_band' => 'balanced',
        ])->assertCreated()->assertJsonPath('data.revision_number', 1);

        $review = ReviewRevision::query()->where('case_id', $case->id)->first();
        $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews/'.$review->id.'/publish')
            ->assertOk()
            ->assertJsonPath('data.published', true);

        $this->assertNotNull($review->fresh()->signed_at);
        $this->assertSame(1, PublicationEvent::query()->where('review_revision_id', $review->id)->where('event', 'published')->count());
    }

    public function test_clinician_with_expired_credential_cannot_create_review(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $case = $this->clinicalCase($patient, $clinician, expired: true);

        // Expired credential → activeClinicalAssignment() is false → view false → 404 (non-enumerating).
        $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews', [
            'source_language' => 'fa',
            'image_adequacy' => 'Adequate', 'observations' => 'O', 'limitations' => 'L', 'options' => 'Op', 'recommended_next_step' => 'N',
        ])->assertNotFound();
    }

    public function test_coordinator_cannot_publish_a_review(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $case = $this->clinicalCase($patient, $clinician, coordinator: $coordinator);
        $document = $this->approvedDocument($case);

        $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews', [
            'source_language' => 'fa', 'clinical_document_id' => $document->id, 'image_adequacy' => 'A', 'observations' => 'O', 'limitations' => 'L', 'options' => 'Op', 'recommended_next_step' => 'N',
        ])->assertCreated();
        $review = ReviewRevision::query()->where('case_id', $case->id)->first();

        // Coordinator is not the assigned clinician → 404
        $this->actingAs($coordinator)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews/'.$review->id.'/publish')
            ->assertNotFound();
        $this->assertNull($review->fresh()->signed_at);
    }

    public function test_other_clinician_cannot_publish_someone_elses_review(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $other = User::factory()->create(['role' => 'clinician']);
        $case = $this->clinicalCase($patient, $clinician);
        $document = $this->approvedDocument($case);

        $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews', [
            'source_language' => 'fa', 'clinical_document_id' => $document->id, 'image_adequacy' => 'A', 'observations' => 'O', 'limitations' => 'L', 'options' => 'Op', 'recommended_next_step' => 'N',
        ])->assertCreated();
        $review = ReviewRevision::query()->where('case_id', $case->id)->first();

        $this->actingAs($other)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews/'.$review->id.'/publish')
            ->assertNotFound();
        $this->assertNull($review->fresh()->signed_at);
    }

    public function test_already_published_review_cannot_be_published_again(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $case = $this->clinicalCase($patient, $clinician);
        $document = $this->approvedDocument($case);

        $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews', [
            'source_language' => 'fa', 'clinical_document_id' => $document->id, 'image_adequacy' => 'A', 'observations' => 'O', 'limitations' => 'L', 'options' => 'Op', 'recommended_next_step' => 'N',
        ])->assertCreated();
        $review = ReviewRevision::query()->where('case_id', $case->id)->first();

        $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews/'.$review->id.'/publish')->assertOk();
        $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews/'.$review->id.'/publish')->assertStatus(409);
    }

    private function approvedDocument(PatientCase $case): ClinicalDocument
    {
        $policy = PolicyVersion::query()->create([
            'policy_key' => 'opg_document_sharing', 'version' => 'clinical-'.Str::random(6), 'locale' => 'fa',
            'content' => 'opg sharing text', 'content_hash' => hash('sha256', 'opg sharing text'), 'published_at' => now(),
        ]);
        $consent = ConsentEvent::query()->create([
            'subject_user_id' => $case->patient_user_id, 'case_id' => $case->id, 'policy_version_id' => $policy->id,
            'purpose' => 'opg_document_sharing', 'decision' => 'accepted', 'locale' => 'fa', 'channel' => 'web',
            'ip_hash' => hash('sha256', 'ip'), 'user_agent_hash' => hash('sha256', 'ua'), 'created_at' => now(),
        ]);

        return ClinicalDocument::query()->create([
            'case_id' => $case->id, 'uploaded_by_user_id' => $case->patient_user_id, 'consent_event_id' => $consent->id,
            'original_name' => encrypt('opg.png'), 'storage_disk' => 'private-opg',
            'storage_key' => 'cases/'.$case->id.'/'.Str::ulid().'.png', 'detected_mime' => 'image/png',
            'byte_size' => 68, 'sha256' => hash('sha256', Str::random()), 'status' => DocumentStatus::Approved,
        ]);
    }

    private function clinicalCase(User $patient, User $clinician, bool $expired = false, ?User $coordinator = null): PatientCase
    {
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.Str::random(6),
            'patient_user_id' => $patient->id,
            'service_type' => 'opg_review',
            'status' => 'clinician_review',
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', 'clinical'),
            'budget_band' => 'balanced',
        ]);
        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(),
            'case_id' => $case->id,
            'assignee_user_id' => $clinician->id,
            'assigned_by_user_id' => $clinician->id,
            'purpose' => 'clinical_review',
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($coordinator) {
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
        DB::table('practitioners')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $clinician->id,
            'licence_number' => encrypt('LIC-'.$clinician->id),
            'licence_hash' => hash('sha256', 'LIC-'.$clinician->id),
            'credential_status' => 'verified',
            'verified_at' => now()->subDay(),
            'expires_at' => $expired ? now()->subSecond() : now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $case;
    }
}
