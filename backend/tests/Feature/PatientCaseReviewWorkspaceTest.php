<?php

namespace Tests\Feature;

use App\Domain\Coordination\Services\ReferralLifecycle;
use App\Models\Clinic;
use App\Models\ClinicalDocument;
use App\Models\ClinicMembership;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReferralGrant;
use App\Models\ReferralProposal;
use App\Models\ReviewRevision;
use App\Models\User;
use App\Support\PanelDemoRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PatientCaseReviewWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_case_response_explicitly_prevents_storage(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->patientCase($patient);

        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_projection_keeps_only_own_current_signed_and_published_reviews_with_first_release_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-03T12:00:00Z'));
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $case = $this->patientCase($patient);
        $document = $this->document($case);
        $draft = $this->review($case, $clinician, $document, 1);
        $signedOnly = $this->review($case, $clinician, $document, 2, signed: true);
        $unsignedPublished = $this->review($case, $clinician, $document, 3);
        $this->publication($unsignedPublished, now()->subHour());
        $old = $this->review($case, $clinician, $document, 4, signed: true);
        $this->publication($old, now()->subHour());
        $current = $this->review($case, $clinician, $document, 5, signed: true);
        $current->update(['supersedes_id' => $old->id, 'source_language' => 'ar']);
        $firstPublished = CarbonImmutable::instance(now()->subMinutes(5));
        $this->publication($current, $firstPublished);
        $this->publication($current, now()->subMinute());
        $this->publication($current, now()->subDay(), 'not_published');
        $foreignCase = $this->patientCase(User::factory()->create(['role' => 'patient']));
        $foreign = $this->review($foreignCase, $clinician, $this->document($foreignCase), 1, signed: true);
        $this->publication($foreign, now()->subWeek());
        $before = $this->clinicalSnapshot();

        $response = $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk();
        $response->assertViewHas('reviews', function ($reviews) use ($current, $firstPublished): bool {
            $review = $reviews->sole();

            return $review->id === $current->id
                && $review->published_at instanceof CarbonImmutable
                && $review->published_at->equalTo($firstPublished)
                && $review->signed_at->equalTo($current->signed_at)
                && $review->source_language === 'ar'
                && $review->revision_number === 5
                && $review->observations === 'PRIVATE-OBSERVATION-'.$current->id
                && ! array_key_exists('clinician_user_id', $review->getAttributes())
                && ! array_key_exists('clinical_document_id', $review->getAttributes());
        });
        foreach ([$draft, $signedOnly, $unsignedPublished, $old, $foreign] as $hidden) {
            $response->assertDontSee('PRIVATE-OBSERVATION-'.$hidden->id);
        }
        $response->assertDontSee($document->storage_key)->assertDontSee('PRIVATE-SCAN-REFERENCE');
        $this->assertSame($before, $this->clinicalSnapshot());
    }

    public function test_document_processing_states_remain_real_without_exposing_deleted_or_foreign_files(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->patientCase($patient);
        $states = ['quarantined', 'scanning', 'approved', 'rejected', 'scan_failed'];
        foreach ($states as $state) {
            $this->document($case, $state);
        }
        $deleted = $this->document($case, 'deleted');
        $deleted->update(['deleted_at' => now()]);
        $foreignCase = $this->patientCase(User::factory()->create(['role' => 'patient']));
        $foreign = $this->document($foreignCase);

        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk()
            ->assertViewHas('reviews', fn ($reviews) => $reviews->isEmpty())
            ->assertViewHas('documents', function ($documents) use ($states): bool {
                $actual = $documents->pluck('status')->all();
                sort($actual);
                sort($states);

                return $actual === $states && $documents->every(fn ($document) => ! isset($document->storage_key));
            })
            ->assertDontSee($deleted->original_name)->assertDontSee($foreign->original_name);
    }

    public function test_actual_clinician_create_and_publish_journey_reaches_only_the_owning_patient(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-03T12:00:00Z'));
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $case = $this->patientCase($patient);
        $document = $this->document($case);
        DB::table('practitioners')->insert([
            'id' => (string) Str::ulid(), 'user_id' => $clinician->id,
            'licence_number' => encrypt('SYNTHETIC-LICENCE'), 'licence_hash' => hash('sha256', 'SYNTHETIC-LICENCE'),
            'credential_status' => 'verified', 'verified_at' => now()->subDay(), 'expires_at' => now()->addYear(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $clinician->id,
            'assigned_by_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'assigned_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $narrative = [
            'source_language' => 'ar', 'image_adequacy' => 'Synthetic adequate image',
            'observations' => 'SYNTHETIC-ACTUAL-PUBLISHED-JOURNEY', 'limitations' => 'Synthetic examination needed',
            'options' => 'Synthetic options', 'recommended_next_step' => 'Synthetic next step', 'budget_band' => 'balanced',
        ];

        $created = $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews', [
            ...$narrative, 'clinical_document_id' => $document->id,
        ])->assertCreated()->assertJsonPath('data.revision_number', 1);
        $reviewId = $created->json('data.id');
        $this->assertDatabaseCount('publication_events', 0);
        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk()
            ->assertDontSee($narrative['observations']);

        $this->travelTo(CarbonImmutable::parse('2026-10-03T12:05:00Z'));
        $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews/'.$reviewId.'/publish')
            ->assertOk()->assertJsonPath('data.published', true)->assertJsonPath('data.id', $reviewId);
        $this->assertDatabaseCount('publication_events', 1);
        $this->assertDatabaseHas('publication_events', [
            'review_revision_id' => $reviewId, 'actor_user_id' => $clinician->id, 'event' => 'published',
            'created_at' => '2026-10-03 12:05:00',
        ]);
        $before = $this->clinicalSnapshot();
        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee($narrative['observations'])
            ->assertViewHas('reviews', function ($reviews) use ($reviewId, $narrative): bool {
                $review = $reviews->sole();
                $this->assertSame($reviewId, $review->id);
                $this->assertSame(1, $review->revision_number);
                $this->assertSame('2026-10-03T12:05:00+00:00', $review->signed_at->toIso8601String());
                $this->assertSame('2026-10-03T12:05:00+00:00', $review->published_at->toIso8601String());
                foreach ($narrative as $field => $value) {
                    $this->assertSame($value, $review->{$field});
                }

                return true;
            });
        $this->actingAs(User::factory()->create(['role' => 'patient']))->get('/en/panel/cases/'.$case->id)
            ->assertNotFound()->assertDontSee($narrative['observations']);
        $this->assertSame($before, $this->clinicalSnapshot());
    }

    public function test_foreign_patient_owner_and_technical_admin_cannot_read_case_clinical_projection(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->patientCase($patient);
        $review = $this->review($case, User::factory()->create(['role' => 'clinician']), $this->document($case), 1, signed: true);
        $this->publication($review, now());
        foreach (['patient', 'owner', 'tech_admin', 'coordinator', 'clinician', 'clinic_rep'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/en/panel/cases/'.$case->id)
                ->assertNotFound()->assertDontSee($review->observations);
        }
        $patient->update(['is_active' => false]);
        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertForbidden()->assertDontSee($review->observations);
    }

    public function test_authorised_coordinator_and_representative_keep_logistics_without_clinical_payloads(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->patientCase($patient);
        $document = $this->document($case);
        $review = $this->review($case, User::factory()->create(['role' => 'clinician']), $document, 1, signed: true);
        $this->publication($review, now());
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $coordinator->id,
            'assigned_by_user_id' => $coordinator->id, 'purpose' => 'coordination', 'assigned_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $representative = User::factory()->create(['role' => 'clinic_rep']);
        $clinic = Clinic::query()->create(['name' => 'Synthetic clinic', 'city' => 'Tehran', 'is_active' => true]);
        ClinicMembership::query()->create(['clinic_id' => $clinic->id, 'user_id' => $representative->id, 'membership_role' => 'contact', 'active_from' => now()->subDay()]);
        $proposal = $this->proposal($case, $clinic, $coordinator);
        ReferralGrant::query()->create([
            'proposal_id' => $proposal->id, 'case_id' => $case->id, 'clinic_id' => $clinic->id,
            'consent_event_id' => $this->consent($case, 'referral_sharing')->id,
            'scope' => ['service_need'], 'granted_at' => now(), 'expires_at' => now()->addDay(),
        ]);
        $before = $this->clinicalSnapshot();

        foreach ([$coordinator, $representative] as $viewer) {
            $response = $this->actingAs($viewer)->get('/en/panel/cases/'.$case->id)->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertViewHas('documents', fn ($documents) => $documents->isEmpty())
                ->assertViewHas('reviews', fn ($reviews) => $reviews->isEmpty())
                ->assertViewHas('draftReviews', fn ($reviews) => $reviews->isEmpty());
            foreach ([$review->observations, $review->image_adequacy, $review->limitations, $review->options, $review->recommended_next_step, $document->storage_key, $document->original_name, 'PRIVATE-SCAN-REFERENCE'] as $secret) {
                $response->assertDontSee($secret);
            }
        }
        $response->assertDontSee('PRIVATE-PATIENT-NAME')->assertDontSee('09120000001');
        $this->assertSame($before, $this->clinicalSnapshot());
    }

    public function test_patient_read_retains_one_referral_view_event_and_never_publishes_clinical_data(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->patientCase($patient);
        $clinic = Clinic::query()->create(['name' => 'Synthetic clinic', 'city' => 'Tehran', 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $proposed = $this->proposal($case, $clinic, $coordinator);
        $withdrawn = $this->proposal($case, $clinic, $coordinator);
        $withdrawn->update(['withdrawn_at' => now()]);
        $accepted = $this->proposal($case, $clinic, $coordinator);
        $accepted->update(['status' => 'accepted']);
        $foreign = $this->proposal($this->patientCase(User::factory()->create(['role' => 'patient'])), $clinic, $coordinator);
        $before = $this->clinicalSnapshot();

        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk();
        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk();
        $events = DB::table('referral_lifecycle_events')->get();
        $this->assertCount(1, $events);
        $this->assertSame($proposed->id, $events->sole()->proposal_id);
        $this->assertSame('viewed', $events->sole()->event_type);
        $this->assertSame($patient->id, $events->sole()->actor_user_id);
        foreach ([$withdrawn, $accepted, $foreign] as $hidden) {
            $this->assertDatabaseMissing('referral_lifecycle_events', ['proposal_id' => $hidden->id]);
        }
        $this->assertSame($before, $this->clinicalSnapshot());
    }

    public function test_demo_patient_read_stays_scoped_and_mutations_remain_hidden(): void
    {
        config()->set('royadarman.panel_demo_access', true);
        $patient = User::factory()->create(['role' => 'patient', 'email' => PanelDemoRegistry::identity('client')['email']]);
        $case = $this->patientCase($patient);
        $case->update(['public_reference' => PanelDemoRegistry::OPG_CASE_REFERENCE]);
        $liveCase = $this->patientCase($patient);
        $before = $this->clinicalSnapshot();

        $this->actingAs($patient)->withSession(['panel_demo' => true, 'panel_demo_user_id' => (string) $patient->id])->get('/en/panel/cases/'.$case->id)
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertDontSee('id="upload-form"', false)->assertDontSee('data-submit-case', false);
        $this->get('/en/panel/cases/'.$liveCase->id)->assertForbidden();
        $this->assertSame($before, $this->clinicalSnapshot());
    }

    public function test_real_withdrawal_projects_the_flag_without_recording_a_view_or_changing_decisions(): void
    {
        $this->freezeTime();
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->patientCase($patient);
        $clinic = Clinic::query()->create(['name' => 'Synthetic clinic', 'city' => 'Tehran', 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $withdrawn = $this->proposal($case, $clinic, $coordinator);
        app(ReferralLifecycle::class)->overrideWithdraw($withdrawn, $coordinator, 'Synthetic withdrawal');
        $before = DB::table('referral_lifecycle_events')->orderBy('id')->get()->toJson();

        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk()
            ->assertViewHas('referrals', fn ($referrals) => $referrals->sole()->id === $withdrawn->id
                && $referrals->sole()->status === 'proposed'
                && $referrals->sole()->effective_status === 'withdrawn'
                && $referrals->sole()->withdrawn_at !== null);
        $this->assertSame($before, DB::table('referral_lifecycle_events')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('referral_grants', 0);
        $this->assertDatabaseHas('referral_proposals', ['id' => $withdrawn->id, 'status' => 'proposed']);
    }

    private function patientCase(User $patient): PatientCase
    {
        return PatientCase::query()->create([
            'public_reference' => 'RD-'.Str::random(8), 'patient_user_id' => $patient->id,
            'patient_name' => 'PRIVATE-PATIENT-NAME', 'patient_mobile' => '09120000001',
            'patient_mobile_hash' => hash('sha256', Str::ulid()), 'service_type' => 'opg_review',
            'status' => 'clinician_review', 'source_language' => 'fa', 'budget_band' => 'balanced', 'version' => 1,
        ]);
    }

    private function consent(PatientCase $case, string $purpose): ConsentEvent
    {
        $policy = PolicyVersion::query()->create([
            'policy_key' => $purpose, 'version' => (string) Str::ulid(), 'locale' => 'fa',
            'content' => 'Synthetic consent', 'content_hash' => hash('sha256', 'Synthetic consent'), 'published_at' => now(),
        ]);

        return ConsentEvent::query()->create([
            'subject_user_id' => $case->patient_user_id, 'case_id' => $case->id, 'policy_version_id' => $policy->id,
            'purpose' => $purpose, 'decision' => 'accepted', 'locale' => 'fa', 'channel' => 'web',
            'ip_hash' => hash('sha256', 'synthetic'), 'user_agent_hash' => hash('sha256', 'synthetic'), 'created_at' => now(),
        ]);
    }

    private function document(PatientCase $case, string $status = 'approved'): ClinicalDocument
    {
        return ClinicalDocument::query()->create([
            'case_id' => $case->id, 'uploaded_by_user_id' => $case->patient_user_id,
            'consent_event_id' => $this->consent($case, 'opg_document_sharing')->id,
            'original_name' => 'PRIVATE-OPG-'.Str::ulid().'.png', 'storage_disk' => 'private-opg',
            'storage_key' => 'private-storage/'.Str::ulid(), 'scan_reference' => 'PRIVATE-SCAN-REFERENCE',
            'detected_mime' => 'image/png', 'byte_size' => 68, 'sha256' => hash('sha256', Str::ulid()), 'status' => $status,
        ]);
    }

    private function review(PatientCase $case, User $clinician, ClinicalDocument $document, int $number, bool $signed = false): ReviewRevision
    {
        $review = ReviewRevision::query()->create([
            'case_id' => $case->id, 'clinician_user_id' => $clinician->id, 'clinical_document_id' => $document->id,
            'revision_number' => $number, 'source_language' => 'fa', 'image_adequacy' => 'PRIVATE-IMAGE-ADEQUACY',
            'observations' => 'placeholder', 'limitations' => 'PRIVATE-LIMITATIONS', 'options' => 'PRIVATE-OPTIONS',
            'recommended_next_step' => 'PRIVATE-NEXT-STEP', 'signed_at' => $signed ? now()->subMinutes(10) : null,
        ]);
        $review->update(['observations' => 'PRIVATE-OBSERVATION-'.$review->id]);

        return $review;
    }

    private function publication(ReviewRevision $review, $at, string $event = 'published'): void
    {
        $review->publicationEvents()->create(['actor_user_id' => $review->clinician_user_id, 'event' => $event, 'created_at' => $at]);
    }

    private function proposal(PatientCase $case, Clinic $clinic, User $coordinator): ReferralProposal
    {
        return ReferralProposal::query()->create([
            'case_id' => $case->id, 'clinic_id' => $clinic->id, 'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed', 'reasoning' => 'Synthetic logistics', 'source_language' => 'fa', 'proposed_at' => now(),
        ]);
    }

    private function clinicalSnapshot(): array
    {
        return collect(['review_revisions', 'publication_events', 'clinical_documents', 'outbox_events'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }
}
