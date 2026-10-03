<?php

namespace Tests\Feature;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\ClinicalDocument;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReviewRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class ClinicianDraftPreviewUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-03 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_empty_workspace_keeps_form_bindings_and_uses_accessible_csp_safe_labels(): void
    {
        [$clinician, $case] = $this->workspace();
        $response = $this->page($clinician, $case)->assertOk()->assertSee('/assets/clinician-case.css', false)
            ->assertSee(__('clinician_case.no_documents'))->assertSee(__('clinician_case.no_drafts'))
            ->assertSee('id="review-form"', false)->assertSee('name="source_language" value="fa"', false);
        $html = $response->getContent();
        $this->assertSame(1, preg_match_all('/<h1\b/i', $html));
        $this->assertDoesNotMatchRegularExpression('/\s(?:style|on[a-z]+)=/i', $html);
        $response->assertSee('for="review-document"', false)->assertSee('id="review-document"', false);
        foreach (['image_adequacy' => 1000, 'observations' => 5000, 'limitations' => 3000, 'options' => 5000, 'recommended_next_step' => 3000] as $field => $limit) {
            $response->assertSee('for="review-'.$field.'"', false)->assertSee('id="review-'.$field.'"', false)
                ->assertSee('name="'.$field.'" required maxlength="'.$limit.'"', false);
        }
        $this->assertSame(file_get_contents(public_path('assets/clinician-case.css')), file_get_contents(base_path('../deployment/webroot/assets/clinician-case.css')));
    }

    public function test_real_api_created_draft_reloads_with_five_escaped_fields_source_language_and_saved_time(): void
    {
        [$clinician, $case] = $this->workspace();
        $document = $this->document($case, '<img src=x onerror=PRIVATE_SOURCE>.png');
        $draft = $this->createDraft($clinician, $case, $document, '<script>OWN-DRAFT-PRIVATE()</script>');
        $ciphertext = DB::table('review_revisions')->where('id', $draft->id)->value('observations');
        $this->assertNotSame($draft->observations, $ciphertext);
        $response = $this->page($clinician, $case)->assertOk()->assertSee('OWN-DRAFT-PRIVATE')->assertDontSee('<script>OWN-DRAFT-PRIVATE()</script>', false)
            ->assertDontSee('<img src=x onerror=PRIVATE_SOURCE>', false)->assertDontSee($ciphertext)
            ->assertSee('2026-10-03 15:30')->assertSee(__('clinician_case.languages.fa'))
            ->assertSee('data-publish-review="'.$draft->id.'"', false)->assertSee(__('clinician_case.draft_help'));
        foreach (['image_adequacy', 'observations', 'limitations', 'options', 'recommended_next_step'] as $field) {
            $response->assertSee('data-draft-field="'.$field.'"', false);
        }
        $this->assertStringContainsString('lang="fa"', $response->getContent());
    }

    public function test_other_clinician_and_signed_drafts_do_not_leak_into_preview(): void
    {
        [$clinician, $case] = $this->workspace();
        $document = $this->document($case);
        $own = $this->createDraft($clinician, $case, $document, 'OWN-UNSIGNED');
        $signed = $this->createDraft($clinician, $case, $document, 'SIGNED-PRIVATE');
        $signed->update(['signed_at' => now()]);
        $foreign = ReviewRevision::query()->create(['case_id' => $case->id, 'clinical_document_id' => $document->id, 'clinician_user_id' => User::factory()->create(['role' => 'clinician'])->id, 'revision_number' => 3, 'source_language' => 'fa', 'image_adequacy' => 'foreign', 'observations' => 'FOREIGN-CLINICIAN-PRIVATE', 'limitations' => 'foreign', 'options' => 'foreign', 'recommended_next_step' => 'foreign']);
        $this->page($clinician, $case)->assertOk()->assertSee('OWN-UNSIGNED')->assertDontSee('SIGNED-PRIVATE')
            ->assertDontSee('FOREIGN-CLINICIAN-PRIVATE')->assertDontSee('data-publish-review="'.$foreign->id.'"', false)
            ->assertSee('data-publish-review="'.$own->id.'"', false);
    }

    public function test_revoked_source_hides_narrative_and_name_but_preserves_own_metadata_with_disabled_publish(): void
    {
        [$clinician, $case] = $this->workspace();
        $document = $this->document($case, 'REVOKED-SOURCE-PRIVATE.png');
        $draft = $this->createDraft($clinician, $case, $document, 'REVOKED-NARRATIVE-PRIVATE');
        $this->page($clinician, $case)->assertOk()->assertSee('REVOKED-NARRATIVE-PRIVATE');
        $document->consentEvent()->update(['revoked_at' => now()]);
        $response = $this->page($clinician, $case)->assertOk()->assertDontSee('REVOKED-NARRATIVE-PRIVATE')
            ->assertDontSee('REVOKED-SOURCE-PRIVATE')->assertSee(__('clinician_case.preview_unavailable'));
        $this->assertMatchesRegularExpression('/<button[^>]+data-publish-review="'.$draft->id.'"[^>]*disabled/', $response->getContent());
        $this->assertNull($draft->fresh()->signed_at);
    }

    public function test_changed_source_status_or_deletion_never_renders_a_saved_narrative(): void
    {
        [$clinician, $case] = $this->workspace();
        foreach ([DocumentStatus::Scanning, DocumentStatus::Rejected, DocumentStatus::Deleted] as $status) {
            $document = $this->document($case, $status->value.'-SOURCE-PRIVATE.png');
            $draft = $this->createDraft($clinician, $case, $document, $status->value.'-NARRATIVE-PRIVATE');
            $document->update(['status' => $status, 'deleted_at' => $status === DocumentStatus::Deleted ? now() : null]);
            $response = $this->page($clinician, $case)->assertOk()->assertDontSee($status->value.'-SOURCE-PRIVATE')
                ->assertDontSee($status->value.'-NARRATIVE-PRIVATE');
            $this->assertMatchesRegularExpression('/<button[^>]+data-publish-review="'.$draft->id.'"[^>]*disabled/', $response->getContent());
        }
    }

    public function test_positive_publish_control_keeps_existing_server_action_and_stops_being_an_unsigned_draft(): void
    {
        [$clinician, $case] = $this->workspace();
        $document = $this->document($case);
        $draft = $this->createDraft($clinician, $case, $document, 'READY-DRAFT');
        $html = $this->page($clinician, $case)->assertOk()->getContent();
        preg_match('/<button[^>]+data-publish-review="'.$draft->id.'"[^>]*>/', $html, $match);
        $this->assertNotEmpty($match);
        $this->assertStringNotContainsString('disabled', $match[0]);
        $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews/'.$draft->id.'/publish')->assertOk();
        $this->assertNotNull($draft->fresh()->signed_at);
        $this->page($clinician, $case)->assertOk()->assertDontSee('data-publish-review="'.$draft->id.'"', false);
    }

    public function test_demo_has_no_save_publish_or_private_document_controls(): void
    {
        [$clinician, $case] = $this->workspace();
        $case->update(['public_reference' => 'TEST-DEMO-UI-CLINIC']);
        $document = $this->document($case);
        $this->createDraft($clinician, $case, $document, 'Synthetic demo draft');
        config()->set('royadarman.panel_demo_access', true);
        $this->actingAs($clinician)->withSession(['panel_demo' => true, 'panel_demo_user_id' => (string) $clinician->id])->get('/en/panel/cases/'.$case->id)
            ->assertOk()->assertDontSee('id="review-form"', false)->assertDontSee('data-publish-review=', false)
            ->assertDontSee('/documents/'.$document->id.'/content', false)->assertSee(__('panel.demo_notice'));
    }

    public function test_original_narrative_and_translated_workspace_labels_render_in_all_locales(): void
    {
        [$clinician, $case] = $this->workspace();
        $this->createDraft($clinician, $case, $this->document($case), 'متن پیش‌نویس ثبت‌شده');
        foreach (['fa', 'ar', 'en'] as $locale) {
            $this->page($clinician, $case, $locale)->assertOk()->assertSee('متن پیش‌نویس ثبت‌شده')
                ->assertSee(__('clinician_case.draft_help'))->assertDontSee('clinician_case.draft_help')
                ->assertSee(__('clinician_case.source_help'))->assertDontSee('clinician_case.source_help');
        }
    }

    public function test_absent_preview_never_falls_back_to_raw_metadata_ciphertext(): void
    {
        [$clinician, $case] = $this->workspace();
        $draft = $this->createDraft($clinician, $case, $this->document($case), 'PRIVATE-PREVIEW');
        $data = $this->page($clinician, $case)->viewData();
        $data['draftReviewPreviews'] = collect();
        $data['draftReviews']->first()->observations = 'RAW-CIPHERTEXT-DO-NOT-RENDER';
        $html = view('panel.case', $data)->render();
        $this->assertStringNotContainsString('RAW-CIPHERTEXT-DO-NOT-RENDER', $html);
        $this->assertStringNotContainsString('PRIVATE-PREVIEW', $html);
        $this->assertStringContainsString(e(__('clinician_case.preview_unavailable')), $html);
        $this->assertMatchesRegularExpression('/<button[^>]+data-publish-review="'.$draft->id.'"[^>]*disabled/', $html);
    }

    private function workspace(): array
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        DB::table('practitioners')->insert(['id' => (string) Str::ulid(), 'user_id' => $clinician->id, 'licence_number' => encrypt('synthetic-licence'), 'licence_hash' => hash('sha256', 'licence-'.$clinician->id), 'credential_status' => 'verified', 'verified_at' => now()->subDay(), 'expires_at' => now()->addYear(), 'created_at' => now(), 'updated_at' => now()]);
        $case = PatientCase::query()->create(['public_reference' => 'CL-'.Str::upper(Str::random(10)), 'patient_user_id' => $patient->id, 'service_type' => 'opg_review', 'status' => 'clinician_review', 'patient_mobile' => '09120000000', 'patient_mobile_hash' => hash('sha256', Str::random()), 'budget_band' => 'balanced', 'source_language' => 'fa', 'version' => 1]);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $clinician->id, 'assigned_by_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return [$clinician, $case];
    }

    private function document(PatientCase $case, string $name = 'authorised-opg.png'): ClinicalDocument
    {
        $policy = PolicyVersion::query()->create(['policy_key' => 'opg_document_sharing', 'version' => 'preview-'.Str::ulid(), 'locale' => 'fa', 'content' => 'synthetic consent', 'content_hash' => hash('sha256', 'synthetic consent'), 'published_at' => now()->subDay()]);
        $consent = ConsentEvent::query()->create(['subject_user_id' => $case->patient_user_id, 'case_id' => $case->id, 'policy_version_id' => $policy->id, 'purpose' => 'opg_document_sharing', 'decision' => 'accepted', 'locale' => 'fa', 'channel' => 'web', 'ip_hash' => hash('sha256', 'synthetic-ip'), 'user_agent_hash' => hash('sha256', 'synthetic-agent'), 'created_at' => now()->subDay()]);

        return ClinicalDocument::query()->create(['case_id' => $case->id, 'uploaded_by_user_id' => $case->patient_user_id, 'consent_event_id' => $consent->id, 'original_name' => $name, 'storage_disk' => 'private-opg', 'storage_key' => 'PRIVATE-STORAGE-'.Str::ulid(), 'detected_mime' => 'image/png', 'byte_size' => 68, 'sha256' => hash('sha256', $name), 'status' => DocumentStatus::Approved]);
    }

    private function createDraft(User $clinician, PatientCase $case, ClinicalDocument $document, string $observations): ReviewRevision
    {
        $response = $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews', ['clinical_document_id' => $document->id, 'source_language' => 'fa', 'image_adequacy' => 'Recorded adequacy', 'observations' => $observations, 'limitations' => 'Recorded limitations', 'options' => 'Recorded options', 'recommended_next_step' => 'Recorded next step'])->assertCreated();

        return ReviewRevision::query()->findOrFail($response->json('data.id'));
    }

    private function page(User $clinician, PatientCase $case, string $locale = 'en'): TestResponse
    {
        return $this->actingAs($clinician)->get('/'.$locale.'/panel/cases/'.$case->id);
    }
}
