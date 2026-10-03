<?php

namespace Tests\Feature;

use App\Models\ClinicalDocument;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReviewRevision;
use App\Models\User;
use App\Support\PanelDemoRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ClinicianDraftPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_actual_create_then_reload_recovers_the_exact_own_saved_draft_without_publishing(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-03T12:00:00Z'));
        config()->set('royadarman.intake_enabled', true);
        [$patient, $clinician, $case, $document] = $this->fixture();
        $narrative = [
            'source_language' => 'ar', 'image_adequacy' => 'PRIVATE-SAVED-ADEQUACY',
            'observations' => 'PRIVATE-SAVED-OBSERVATION', 'limitations' => 'PRIVATE-SAVED-LIMITATIONS',
            'options' => 'PRIVATE-SAVED-OPTIONS', 'recommended_next_step' => 'PRIVATE-SAVED-NEXT',
        ];
        $created = $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews', [
            ...$narrative, 'clinical_document_id' => $document->id,
        ])->assertCreated()->assertJsonPath('data.revision_number', 1);
        $id = $created->json('data.id');
        $before = $this->clinicalSnapshot();

        $response = $this->actingAs($clinician)->get('/en/panel/cases/'.$case->id)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertViewHas('draftReviewPreviews');
        $previews = $response->viewData('draftReviewPreviews');
        $this->assertSame([$id], $previews->keys()->all());
        $preview = $previews->get($id);
        $this->assertSame($id, $preview->id);
        $this->assertSame(1, $preview->revision_number);
        $this->assertSame($document->original_name, $preview->source_name);
        $this->assertInstanceOf(CarbonImmutable::class, $preview->created_at);
        $this->assertSame('2026-10-03T12:00:00+00:00', $preview->created_at->toIso8601String());
        foreach ($narrative as $key => $value) {
            $this->assertSame($value, $preview->{$key});
        }
        $this->assertSame([
            'id', 'revision_number', 'source_language', 'created_at', 'source_name',
            'image_adequacy', 'observations', 'limitations', 'options', 'recommended_next_step',
        ], array_keys(get_object_vars($preview)));
        $response->assertDontSee($document->storage_key)->assertDontSee('PRIVATE-SCANNER-REFERENCE');
        $this->assertSame($before, $this->clinicalSnapshot());
        $this->assertNull(ReviewRevision::query()->findOrFail($id)->signed_at);
        $this->assertDatabaseCount('publication_events', 0);
        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk()->assertDontSee($narrative['observations']);
    }

    public function test_other_authors_cases_and_signed_rows_never_enter_preview_collection(): void
    {
        [$patient, $clinician, $case, $document] = $this->fixture();
        $own = $this->draft($case, $clinician, $document, 1);
        $foreignAuthor = $this->draft($case, User::factory()->create(['role' => 'clinician']), $document, 2);
        $signed = $this->draft($case, $clinician, $document, 3);
        $signed->update(['signed_at' => now()]);
        [$otherPatient, $sameClinician, $otherCase, $otherDocument] = $this->fixture($clinician);
        $foreignCase = $this->draft($otherCase, $clinician, $otherDocument, 1);
        $before = $this->clinicalSnapshot();

        $response = $this->actingAs($clinician)->get('/en/panel/cases/'.$case->id)->assertOk()->assertViewHas('draftReviewPreviews');
        $this->assertSame([$own->id], $response->viewData('draftReviewPreviews')->keys()->all());
        $this->assertSame([$own->id], $response->viewData('draftReviews')->pluck('id')->all());
        foreach ([$foreignAuthor, $signed, $foreignCase] as $hidden) {
            $response->assertDontSee($hidden->observations);
            $this->assertFalse($response->viewData('draftReviewPreviews')->has($hidden->id));
        }
        $this->assertSame($before, $this->clinicalSnapshot());
    }

    public function test_unavailable_source_withholds_preview_without_erasing_own_draft_metadata(): void
    {
        [$patient, $clinician, $case] = $this->fixture();
        $ids = [];
        foreach (['revoked', 'rejected_consent', 'null_consent', 'deleted', 'quarantined', 'scanning', 'scan_failed', 'rejected'] as $state) {
            $document = $this->document($case);
            match ($state) {
                'revoked' => $document->consentEvent()->update(['revoked_at' => now()]),
                'rejected_consent' => $document->consentEvent()->update(['decision' => 'rejected']),
                'null_consent' => $document->update(['consent_event_id' => null]),
                'deleted' => $document->update(['deleted_at' => now()]),
                default => $document->update(['status' => $state]),
            };
            $ids[] = $this->draft($case, $clinician, $document, count($ids) + 1)->id;
        }
        $before = $this->clinicalSnapshot();

        $response = $this->actingAs($clinician)->get('/en/panel/cases/'.$case->id)->assertOk()->assertViewHas('draftReviewPreviews');
        $this->assertTrue($response->viewData('draftReviewPreviews')->isEmpty());
        $this->assertEqualsCanonicalizing($ids, $response->viewData('draftReviews')->pluck('id')->all());
        foreach ($ids as $id) {
            $response->assertDontSee('PRIVATE-DRAFT-'.$id);
        }
        $this->assertSame($before, $this->clinicalSnapshot());
    }

    public function test_foreign_case_source_is_not_readable_even_when_same_clinician_has_both_assignments(): void
    {
        [$patient, $clinician, $case] = $this->fixture();
        [$otherPatient, $sameClinician, $otherCase, $otherDocument] = $this->fixture($clinician);
        $draft = $this->draft($case, $clinician, $otherDocument, 1);
        $before = $this->clinicalSnapshot();

        $response = $this->actingAs($clinician)->get('/en/panel/cases/'.$case->id)->assertOk()->assertViewHas('draftReviewPreviews');
        $this->assertTrue($response->viewData('draftReviewPreviews')->isEmpty());
        $this->assertSame([$draft->id], $response->viewData('draftReviews')->pluck('id')->all());
        $response->assertDontSee($draft->observations)->assertDontSee($otherDocument->original_name);
        $this->assertSame($before, $this->clinicalSnapshot());
    }

    public function test_consent_revocation_excludes_the_payload_before_decryption_on_next_read(): void
    {
        [$patient, $clinician, $case, $document] = $this->fixture();
        $draft = $this->draft($case, $clinician, $document, 1);
        $this->actingAs($clinician)->get('/en/panel/cases/'.$case->id)->assertOk()
            ->assertViewHas('draftReviewPreviews', fn ($previews) => $previews->has($draft->id));
        $document->consentEvent()->update(['revoked_at' => now()]);
        // A broad read/decrypt followed by a presentation filter would now fail.
        DB::table('review_revisions')->where('id', $draft->id)->update(['observations' => 'not-valid-encrypted-clinical-text']);
        $before = $this->clinicalSnapshot();

        $this->actingAs($clinician)->get('/en/panel/cases/'.$case->id)->assertOk()
            ->assertViewHas('draftReviewPreviews', fn ($previews) => $previews->isEmpty())
            ->assertViewHas('draftReviews', fn ($drafts) => $drafts->pluck('id')->all() === [$draft->id])
            ->assertDontSee('not-valid-encrypted-clinical-text')->assertDontSee($draft->observations);
        $this->assertSame($before, $this->clinicalSnapshot());
    }

    public function test_assignment_credential_and_account_revocations_are_observed_before_preview_reads(): void
    {
        foreach (['released', 'expired', 'unverified', 'inactive'] as $state) {
            [$patient, $clinician, $case, $document] = $this->fixture();
            $draft = $this->draft($case, $clinician, $document, 1);
            $this->actingAs($clinician)->get('/en/panel/cases/'.$case->id)->assertOk()
                ->assertViewHas('draftReviewPreviews')
                ->assertViewHas('draftReviewPreviews', fn ($previews) => $previews->has($draft->id));
            match ($state) {
                'released' => DB::table('case_assignments')->where('case_id', $case->id)->where('assignee_user_id', $clinician->id)->update(['released_at' => now()]),
                'expired' => DB::table('practitioners')->where('user_id', $clinician->id)->update(['expires_at' => now()]),
                'unverified' => DB::table('practitioners')->where('user_id', $clinician->id)->update(['credential_status' => 'pending']),
                'inactive' => $clinician->update(['is_active' => false]),
            };
            $before = $this->clinicalSnapshot();
            $this->actingAs($clinician->fresh())->get('/en/panel/cases/'.$case->id)
                ->assertStatus($state === 'inactive' ? 403 : 404)->assertDontSee($draft->observations);
            $this->assertSame($before, $this->clinicalSnapshot());
        }
    }

    public function test_nonclinical_roles_never_receive_the_draft_projection(): void
    {
        [$patient, $clinician, $case, $document] = $this->fixture();
        $draft = $this->draft($case, $clinician, $document, 1);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $this->assign($case, $coordinator, 'coordination');
        $before = $this->clinicalSnapshot();
        foreach ([$patient, $coordinator] as $allowed) {
            $response = $this->actingAs($allowed)->get('/en/panel/cases/'.$case->id)->assertOk()->assertDontSee($draft->observations);
            $this->assertArrayNotHasKey('draftReviewPreviews', $response->viewData());
            $this->assertTrue($response->viewData('draftReviews')->isEmpty());
        }
        foreach (['owner', 'tech_admin', 'clinic_rep', 'clinician'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/en/panel/cases/'.$case->id)
                ->assertNotFound()->assertDontSee($draft->observations);
        }
        $this->assertSame($before, $this->clinicalSnapshot());
    }

    public function test_demo_preview_is_synthetic_and_does_not_enable_mutations_or_other_cases(): void
    {
        config()->set('royadarman.panel_demo_access', true);
        [$patient, $clinician, $case, $document] = $this->fixture();
        $clinician->update(['email' => PanelDemoRegistry::identity('clinician')['email']]);
        $case->update(['public_reference' => PanelDemoRegistry::OPG_CASE_REFERENCE]);
        $draft = $this->draft($case, $clinician, $document, 1);
        [$otherPatient, $sameClinician, $liveCase] = $this->fixture($clinician);
        $before = $this->clinicalSnapshot();

        $this->actingAs($clinician)->withSession(['panel_demo' => true, 'panel_demo_user_id' => (string) $clinician->id])
            ->get('/en/panel/cases/'.$case->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertViewHas('draftReviewPreviews')
            ->assertViewHas('draftReviewPreviews', fn ($previews) => $previews->has($draft->id))
            ->assertDontSee('data-publish-review=', false)->assertDontSee('id="review-form"', false);
        $this->get('/en/panel/cases/'.$liveCase->id)->assertForbidden();
        $this->postJson('/api/v1/staff/cases/'.$case->id.'/reviews/'.$draft->id.'/publish')->assertForbidden();
        $this->assertSame($before, $this->clinicalSnapshot());
    }

    private function fixture(?User $clinician = null): array
    {
        $patient = User::factory()->create(['role' => 'patient']);
        if ($clinician === null) {
            $clinician = User::factory()->create(['role' => 'clinician']);
            DB::table('practitioners')->insert([
                'id' => (string) Str::ulid(), 'user_id' => $clinician->id,
                'licence_number' => encrypt('SYNTHETIC-LICENCE-'.$clinician->id), 'licence_hash' => hash('sha256', (string) $clinician->id),
                'credential_status' => 'verified', 'verified_at' => now()->subDay(), 'expires_at' => now()->addYear(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.Str::random(8), 'patient_user_id' => $patient->id,
            'patient_mobile' => '09120000001', 'patient_mobile_hash' => hash('sha256', Str::ulid()),
            'service_type' => 'opg_review', 'status' => 'clinician_review', 'source_language' => 'fa', 'budget_band' => 'balanced',
        ]);
        $this->assign($case, $clinician, 'clinical_review');

        return [$patient, $clinician, $case, $this->document($case)];
    }

    private function assign(PatientCase $case, User $user, string $purpose): void
    {
        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $user->id,
            'assigned_by_user_id' => $user->id, 'purpose' => $purpose, 'assigned_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function document(PatientCase $case): ClinicalDocument
    {
        $policy = PolicyVersion::query()->create([
            'policy_key' => 'opg_document_sharing', 'version' => (string) Str::ulid(), 'locale' => 'fa',
            'content' => 'Synthetic consent', 'content_hash' => hash('sha256', 'Synthetic consent'), 'published_at' => now(),
        ]);
        $consent = ConsentEvent::query()->create([
            'subject_user_id' => $case->patient_user_id, 'case_id' => $case->id, 'policy_version_id' => $policy->id,
            'purpose' => 'opg_document_sharing', 'decision' => 'accepted', 'locale' => 'fa', 'channel' => 'web',
            'ip_hash' => hash('sha256', 'synthetic'), 'user_agent_hash' => hash('sha256', 'synthetic'), 'created_at' => now(),
        ]);

        return ClinicalDocument::query()->create([
            'case_id' => $case->id, 'uploaded_by_user_id' => $case->patient_user_id, 'consent_event_id' => $consent->id,
            'original_name' => 'SYNTHETIC-SOURCE-'.Str::ulid().'.png', 'storage_disk' => 'private-opg',
            'storage_key' => 'PRIVATE-STORAGE-'.Str::ulid(), 'scan_reference' => 'PRIVATE-SCANNER-REFERENCE',
            'detected_mime' => 'image/png', 'byte_size' => 68, 'sha256' => hash('sha256', Str::ulid()), 'status' => 'approved',
        ]);
    }

    private function draft(PatientCase $case, User $clinician, ClinicalDocument $document, int $number): ReviewRevision
    {
        $draft = ReviewRevision::query()->create([
            'case_id' => $case->id, 'clinician_user_id' => $clinician->id, 'clinical_document_id' => $document->id,
            'revision_number' => $number, 'source_language' => 'fa', 'image_adequacy' => 'Synthetic adequacy',
            'observations' => 'placeholder', 'limitations' => 'Synthetic limitations', 'options' => 'Synthetic options',
            'recommended_next_step' => 'Synthetic next step',
        ]);
        $draft->update(['observations' => 'PRIVATE-DRAFT-'.$draft->id]);

        return $draft;
    }

    private function clinicalSnapshot(): array
    {
        return collect(['review_revisions', 'publication_events', 'clinical_documents', 'outbox_events'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }
}
