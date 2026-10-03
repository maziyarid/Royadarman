<?php

namespace Tests\Feature;

use App\Models\ClinicalDocument;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReviewRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReviewDeletedSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_but_deleted_source_cannot_create_a_review_or_mutate_clinical_or_audit_records(): void
    {
        [$clinician, $case, $document] = $this->fixture();
        $document->update(['deleted_at' => now()]);
        $before = $this->snapshot();

        $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews', $this->payload($document))
            ->assertStatus(422)->assertJsonPath('error.code', 'review.document_not_approved');
        $this->assertSame($before, $this->snapshot());
        $this->assertDatabaseCount('review_revisions', 0);
        $this->assertDatabaseCount('publication_events', 0);
    }

    public function test_source_deleted_after_actual_draft_creation_cannot_publish_or_mutate_any_audit_record(): void
    {
        [$clinician, $case, $document] = $this->fixture();
        $created = $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews', $this->payload($document))
            ->assertCreated();
        $reviewId = $created->json('data.id');
        $document->update(['deleted_at' => now()]);
        $before = $this->snapshot();

        $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews/'.$reviewId.'/publish')
            ->assertStatus(422)->assertJsonPath('error.code', 'review.document_not_approved');
        $this->assertSame($before, $this->snapshot());
        $this->assertNull(ReviewRevision::query()->findOrFail($reviewId)->signed_at);
        $this->assertDatabaseCount('publication_events', 0);
    }

    public function test_clean_approved_source_retains_the_existing_create_and_publish_positive_path(): void
    {
        [$clinician, $case, $document] = $this->fixture();
        $created = $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews', $this->payload($document))
            ->assertCreated()->assertJsonPath('data.revision_number', 1);
        $reviewId = $created->json('data.id');

        $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews/'.$reviewId.'/publish')
            ->assertOk()->assertJsonPath('data.published', true);
        $this->assertNotNull(ReviewRevision::query()->findOrFail($reviewId)->signed_at);
        $this->assertDatabaseCount('review_revisions', 1);
        $this->assertDatabaseCount('publication_events', 1);
        $this->assertDatabaseHas('publication_events', ['review_revision_id' => $reviewId, 'actor_user_id' => $clinician->id, 'event' => 'published']);
    }

    private function fixture(): array
    {
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        DB::table('practitioners')->insert([
            'id' => (string) Str::ulid(), 'user_id' => $clinician->id,
            'licence_number' => encrypt('SYNTHETIC-LICENCE'), 'licence_hash' => hash('sha256', 'SYNTHETIC-LICENCE'),
            'credential_status' => 'verified', 'verified_at' => now()->subDay(), 'expires_at' => now()->addYear(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.Str::random(8), 'patient_user_id' => $patient->id,
            'patient_mobile' => '09120000001', 'patient_mobile_hash' => hash('sha256', Str::ulid()),
            'service_type' => 'opg_review', 'status' => 'clinician_review', 'source_language' => 'fa', 'budget_band' => 'balanced',
        ]);
        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $clinician->id,
            'assigned_by_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'assigned_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $policy = PolicyVersion::query()->create([
            'policy_key' => 'opg_document_sharing', 'version' => (string) Str::ulid(), 'locale' => 'fa',
            'content' => 'Synthetic consent', 'content_hash' => hash('sha256', 'Synthetic consent'), 'published_at' => now(),
        ]);
        $consent = ConsentEvent::query()->create([
            'subject_user_id' => $patient->id, 'case_id' => $case->id, 'policy_version_id' => $policy->id,
            'purpose' => 'opg_document_sharing', 'decision' => 'accepted', 'locale' => 'fa', 'channel' => 'web',
            'ip_hash' => hash('sha256', 'synthetic'), 'user_agent_hash' => hash('sha256', 'synthetic'), 'created_at' => now(),
        ]);
        $document = ClinicalDocument::query()->create([
            'case_id' => $case->id, 'uploaded_by_user_id' => $patient->id, 'consent_event_id' => $consent->id,
            'original_name' => 'synthetic-source.png', 'storage_disk' => 'private-opg', 'storage_key' => 'synthetic-private/'.Str::ulid(),
            'detected_mime' => 'image/png', 'byte_size' => 68, 'sha256' => hash('sha256', Str::ulid()), 'status' => 'approved',
        ]);

        return [$clinician, $case, $document];
    }

    private function payload(ClinicalDocument $document): array
    {
        return [
            'clinical_document_id' => $document->id, 'source_language' => 'fa', 'image_adequacy' => 'Synthetic image adequacy',
            'observations' => 'Synthetic observations', 'limitations' => 'Synthetic limitations', 'options' => 'Synthetic options',
            'recommended_next_step' => 'Synthetic next step',
        ];
    }

    private function snapshot(): array
    {
        return collect(['review_revisions', 'publication_events', 'audit_events', 'outbox_events'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }
}
