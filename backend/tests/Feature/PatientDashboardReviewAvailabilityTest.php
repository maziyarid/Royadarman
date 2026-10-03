<?php

namespace Tests\Feature;

use App\Domain\Dashboard\DashboardService;
use App\Models\ClinicalDocument;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReviewRevision;
use App\Models\User;
use App\Support\PanelDemoRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PatientDashboardReviewAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsigned_publication_is_not_advertised_when_case_page_withholds_it(): void
    {
        [$patient, $case, $review] = $this->fixture();
        $this->publishEvent($review);
        $this->assertAvailability($patient, false);
        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk()
            ->assertViewHas('reviews', fn ($reviews) => $reviews->isEmpty());
    }

    public function test_signed_only_or_nonpublication_events_remain_unavailable(): void
    {
        [$patient, $case, $review] = $this->fixture(signed: true);
        $this->publishEvent($review, 'not_published');
        $this->assertAvailability($patient, false);
        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk()
            ->assertViewHas('reviews', fn ($reviews) => $reviews->isEmpty());
    }

    public function test_any_superseder_withholds_old_release_matching_existing_patient_page(): void
    {
        [$patient, $case, $old] = $this->fixture(signed: true);
        $this->publishEvent($old);
        $this->revision($case, $old->clinician_user_id, $old->clinical_document_id, 2)
            ->update(['supersedes_id' => $old->id]);
        $this->assertAvailability($patient, false);
        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk()
            ->assertViewHas('reviews', fn ($reviews) => $reviews->isEmpty());
    }

    public function test_current_release_remains_available_after_superseding_previous_release(): void
    {
        [$patient, $case, $old] = $this->fixture(signed: true);
        $this->publishEvent($old);
        $current = $this->revision($case, $old->clinician_user_id, $old->clinical_document_id, 2, signed: true);
        $current->update(['supersedes_id' => $old->id]);
        $this->publishEvent($current);
        $before = $this->snapshot();
        $this->assertAvailability($patient, true);
        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk()
            ->assertViewHas('reviews', fn ($reviews) => $reviews->sole()->id === $current->id);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_patient_availability_reads_an_aggregate_without_hydrating_review_text_columns(): void
    {
        [$patient, $case, $review] = $this->fixture(signed: true);
        $this->publishEvent($review);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->assertAvailability($patient, true);
        $reviewReads = array_filter($queries, fn ($sql) => preg_match('/^select [^(]* from ["`]review_revisions["`]/i', $sql));
        $this->assertSame([], array_values($reviewReads), 'Dashboard must use a correlated aggregate, not hydrate clinical revisions.');
    }

    public function test_actual_create_publish_patient_dashboard_case_journey_preserves_release_authority(): void
    {
        config()->set('royadarman.intake_enabled', true);
        [$patient, $case, $draft] = $this->fixture();
        $clinician = User::query()->findOrFail($draft->clinician_user_id);
        $this->assign($clinician, $case);
        DB::table('practitioners')->insert([
            'id' => (string) Str::ulid(), 'user_id' => $clinician->id,
            'licence_number' => encrypt('SYNTHETIC-LICENCE'), 'licence_hash' => hash('sha256', Str::ulid()),
            'credential_status' => 'verified', 'verified_at' => now()->subDay(), 'expires_at' => now()->addYear(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $created = $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews', [
            'clinical_document_id' => $draft->clinical_document_id, 'source_language' => 'fa',
            'image_adequacy' => 'SYNTHETIC-IMAGE', 'observations' => 'PRIVATE-ACTUAL-RELEASE',
            'limitations' => 'SYNTHETIC-LIMIT', 'options' => 'SYNTHETIC-OPTION', 'recommended_next_step' => 'SYNTHETIC-NEXT',
        ])->assertCreated();
        $this->assertAvailability($patient, false);
        $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews/'.$created->json('data.id').'/publish')->assertOk();
        $before = $this->snapshot();
        $this->assertAvailability($patient, true);
        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk()->assertSee('PRIVATE-ACTUAL-RELEASE');
        $this->actingAs(User::factory()->create(['role' => 'patient']))->get('/en/panel/cases/'.$case->id)->assertNotFound();
        $this->assertSame($before, $this->snapshot());
        $this->assertDatabaseCount('outbox_events', 0);
        $this->assertDatabaseCount('notification_deliveries', 0);
    }

    public function test_foreign_case_and_nonpatient_projections_do_not_leak_clinical_text(): void
    {
        [$patient, $case, $review] = $this->fixture(signed: true);
        $this->publishEvent($review);
        $other = User::factory()->create(['role' => 'patient']);
        $this->actingAs($other)->getJson('/api/v1/dashboard')->assertOk()->assertJsonCount(0, 'data.cases')
            ->assertDontSee($case->id)->assertDontSee('PRIVATE-REVIEW-TEXT');
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $this->assign($coordinator, $case, 'coordination');
        $case->update(['current_coordinator_id' => $coordinator->id]);
        $this->actingAs($coordinator)->getJson('/api/v1/dashboard')->assertOk()->assertDontSee('PRIVATE-REVIEW-TEXT');
        $this->get('/en/panel/cases/'.$case->id)->assertOk()->assertDontSee('PRIVATE-REVIEW-TEXT');
    }

    public function test_demo_case_filter_remains_unchanged(): void
    {
        [$patient, $case, $review] = $this->fixture(signed: true);
        $this->publishEvent($review);
        $this->assertCount(0, app(DashboardService::class)->build($patient, true)['cases']);
        $case->update(['public_reference' => PanelDemoRegistry::OPG_CASE_REFERENCE]);
        $projection = app(DashboardService::class)->build($patient, true)['cases']->sole();
        $this->assertSame($case->id, $projection['id']);
        $this->assertTrue($projection['has_published_review']);
    }

    private function assertAvailability(User $patient, bool $available): void
    {
        $this->actingAs($patient)->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonCount(1, 'data.cases')->assertJsonPath('data.cases.0.has_published_review', $available)
            ->assertDontSee('PRIVATE-REVIEW-TEXT')->assertDontSee('PRIVATE-STORAGE');
    }

    private function fixture(bool $signed = false): array
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.Str::random(8), 'patient_user_id' => $patient->id,
            'patient_mobile' => '09120000001', 'patient_mobile_hash' => hash('sha256', Str::ulid()),
            'service_type' => 'opg_review', 'status' => 'clinician_review', 'source_language' => 'fa', 'budget_band' => 'balanced', 'version' => 1,
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
            'original_name' => 'SYNTHETIC-OPG.png', 'storage_disk' => 'private-opg', 'storage_key' => 'PRIVATE-STORAGE',
            'detected_mime' => 'image/png', 'byte_size' => 68, 'sha256' => hash('sha256', Str::ulid()), 'status' => 'approved',
        ]);

        return [$patient, $case, $this->revision($case, $clinician->id, $document->id, 1, $signed)];
    }

    private function revision(PatientCase $case, int $clinicianId, string $documentId, int $number, bool $signed = false): ReviewRevision
    {
        return ReviewRevision::query()->create([
            'case_id' => $case->id, 'clinician_user_id' => $clinicianId, 'clinical_document_id' => $documentId,
            'revision_number' => $number, 'source_language' => 'fa', 'signed_at' => $signed ? now() : null,
            'image_adequacy' => 'SYNTHETIC-IMAGE', 'observations' => 'PRIVATE-REVIEW-TEXT',
            'limitations' => 'SYNTHETIC-LIMIT', 'options' => 'SYNTHETIC-OPTION', 'recommended_next_step' => 'SYNTHETIC-NEXT',
        ]);
    }

    private function publishEvent(ReviewRevision $review, string $event = 'published'): void
    {
        $review->publicationEvents()->create(['actor_user_id' => $review->clinician_user_id, 'event' => $event, 'created_at' => now()]);
    }

    private function assign(User $user, PatientCase $case, string $purpose = 'clinical_review'): void
    {
        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $user->id,
            'assigned_by_user_id' => $user->id, 'purpose' => $purpose, 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function snapshot(): array
    {
        return collect(['review_revisions', 'publication_events', 'clinical_documents', 'outbox_events'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }
}
