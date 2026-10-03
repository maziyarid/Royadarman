<?php

namespace Tests\Feature;

use App\Domain\Coordination\Services\ReferralLifecycle;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\Clinic;
use App\Models\ClinicalDocument;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReferralProposal;
use App\Models\ReviewRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class PatientCaseReviewUiTest extends TestCase
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

    public function test_no_document_has_a_clear_state_and_existing_upload_binding(): void
    {
        [$patient, $case] = $this->patientCase();
        $response = $this->page($patient, $case)->assertOk();
        $response->assertSee('/assets/patient-case.css', false)->assertSee('data-review-state="no_documents"', false)
            ->assertSee(__('patient_case.no_document'))->assertSee(__('patient_case.no_review'))
            ->assertSee('id="upload-form"', false)->assertSee('name="document"', false)
            ->assertSee('accept="image/jpeg,image/png"', false)->assertSee(__('patient_case.file_check_note'));
        $html = $response->getContent();
        $this->assertSame(1, preg_match_all('/<h1\b/i', $html));
        $this->assertDoesNotMatchRegularExpression('/\s(?:style|on[a-z]+)=/i', $html);
        $this->assertSame(file_get_contents(public_path('assets/patient-case.css')), file_get_contents(base_path('../deployment/webroot/assets/patient-case.css')));
    }

    public function test_real_file_states_are_distinct_and_only_approved_images_keep_content_links(): void
    {
        [$patient, $case] = $this->patientCase();
        foreach ([DocumentStatus::Quarantined, DocumentStatus::Scanning, DocumentStatus::Approved, DocumentStatus::Rejected, DocumentStatus::ScanFailed] as $status) {
            $documents[$status->value] = $this->document($case, $status, $status->value.'.png');
        }
        $deleted = $this->document($case, DocumentStatus::Deleted, 'DELETED-PRIVATE.png');
        $deleted->update(['deleted_at' => now()]);
        $response = $this->page($patient, $case)->assertOk()->assertSee('data-review-state="processing"', false);
        foreach ($documents as $status => $document) {
            $response->assertSee(__('patient_case.document_status.'.$status));
            $content = '/api/v1/cases/'.$case->id.'/documents/'.$document->id.'/content';
            $status === 'approved' ? $response->assertSee($content, false) : $response->assertDontSee($content, false);
            $response->assertDontSee($document->storage_key);
        }
        $response->assertDontSee('DELETED-PRIVATE.png')->assertSee(__('patient_case.scan_failed_help'));
    }

    public function test_file_approval_does_not_imply_a_released_or_healthy_dental_assessment(): void
    {
        [$patient, $case] = $this->patientCase();
        $this->document($case);
        $this->page($patient, $case)->assertOk()->assertSee('data-review-state="awaiting_review"', false)
            ->assertSee(__('patient_case.file_check_note'))->assertSee(__('patient_case.no_review'))
            ->assertSee(__('patient_case.review_wait_help'));
    }

    public function test_signed_released_narrative_has_five_fields_language_and_real_first_publication_time(): void
    {
        [$patient, $case] = $this->patientCase();
        $document = $this->document($case);
        $review = $this->review($case, $document, 1, '<script>PRIVATE-OWN-OBSERVATION()</script>');
        $review->publicationEvents()->create(['actor_user_id' => $review->clinician_user_id, 'event' => 'published', 'created_at' => now()->subMinutes(15)]);
        $response = $this->page($patient, $case)->assertOk()->assertSee('data-review-state="released"', false)
            ->assertSee('PRIVATE-OWN-OBSERVATION')->assertDontSee('<script>PRIVATE-OWN-OBSERVATION()</script>', false)
            ->assertSee('2026-10-03 13:30')->assertSee('2026-10-03 14:30')->assertDontSee('2026-10-03 15:15')
            ->assertSee('datetime="2026-10-03T10:00:00+00:00"', false)->assertSee('datetime="2026-10-03T11:00:00+00:00"', false)
            ->assertSee(__('patient_case.languages.fa'))->assertSee(__('patient_case.timezone_hint'));
        foreach (['image_adequacy', 'observations', 'limitations', 'options', 'recommended_next_step'] as $field) {
            $response->assertSee('data-review-field="'.$field.'"', false);
        }
        $this->assertStringContainsString('lang="fa"', $response->getContent());
    }

    public function test_unsigned_signed_only_and_superseded_text_never_become_patient_reports(): void
    {
        [$patient, $case] = $this->patientCase();
        $document = $this->document($case);
        $earlier = $this->review($case, $document, 1, 'SUPERSEDED-PRIVATE');
        $current = $this->review($case, $document, 2, 'CURRENT-PUBLISHED');
        $current->update(['supersedes_id' => $earlier->id]);
        $this->review($case, $document, 3, 'SIGNED-ONLY-PRIVATE', false);
        $this->review($case, $document, 4, 'UNSIGNED-PUBLISHED-PRIVATE', true, false);
        $before = DB::table('publication_events')->count();
        $this->page($patient, $case)->assertOk()->assertSee('CURRENT-PUBLISHED')
            ->assertDontSee('SUPERSEDED-PRIVATE')->assertDontSee('SIGNED-ONLY-PRIVATE')->assertDontSee('UNSIGNED-PUBLISHED-PRIVATE');
        $this->assertSame($before, DB::table('publication_events')->count());
        $this->assertSame(4, ReviewRevision::query()->count());
    }

    public function test_withdrawn_referral_drops_stale_decision_controls_but_real_proposal_keeps_both(): void
    {
        [$patient, $case] = $this->patientCase();
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $clinic = Clinic::query()->create(['name' => '<script>CLINIC-NAME</script>', 'city' => 'tehran', 'is_active' => true]);
        $make = fn () => ReferralProposal::query()->create(['case_id' => $case->id, 'clinic_id' => $clinic->id, 'proposed_by_user_id' => $coordinator->id, 'status' => 'proposed', 'reasoning' => 'PRIVATE-REFERRAL-REASON', 'source_language' => 'fa', 'proposed_at' => now()->subDay()]);
        $active = $make();
        $withdrawn = $make();
        app(ReferralLifecycle::class)->overrideWithdraw($withdrawn, $coordinator, 'synthetic withdrawal');
        $response = $this->page($patient, $case)->assertOk()->assertSee(__('panel_case.referral_status.withdrawn'))
            ->assertSee('data-referral-id="'.$active->id.'"', false)->assertDontSee('data-referral-id="'.$withdrawn->id.'"', false)
            ->assertSee('data-referral-decision="accepted"', false)->assertSee('data-referral-decision="declined"', false)
            ->assertDontSee('<script>CLINIC-NAME</script>', false)->assertDontSee('PRIVATE-REFERRAL-REASON');
        $this->assertSame(2, substr_count($response->getContent(), 'data-referral-decision='));
        $this->assertSame('proposed', $withdrawn->fresh()->status);
    }

    public function test_foreign_patient_and_unassigned_roles_cannot_read_own_report_text(): void
    {
        [$patient, $case] = $this->patientCase();
        $this->review($case, $this->document($case), 1, 'OWN-PRIVATE-REPORT');
        foreach (['patient', 'owner', 'tech_admin', 'coordinator', 'clinic_rep', 'clinician'] as $role) {
            $this->page(User::factory()->create(['role' => $role]), $case)->assertNotFound()->assertDontSee('OWN-PRIVATE-REPORT');
        }
        $patient->update(['is_active' => false]);
        $this->page($patient, $case)->assertForbidden();
    }

    public function test_patient_draft_preserves_the_existing_submit_control_and_secure_upload_input(): void
    {
        [$patient, $case] = $this->patientCase();
        $case->update(['status' => 'draft']);
        $this->page($patient, $case)->assertOk()->assertSee('data-submit-case', false)
            ->assertSee('id="upload-form"', false)->assertSee('id="opg-file"', false)
            ->assertSee('data-case-version="1"', false)->assertSee('data-source-language="fa"', false);
    }

    public function test_demo_keeps_upload_and_document_content_controls_hidden(): void
    {
        [$patient, $case] = $this->patientCase();
        $case->update(['public_reference' => 'TEST-DEMO-UI-001']);
        $document = $this->document($case);
        $clinic = Clinic::query()->create(['name' => 'Synthetic demo clinic', 'city' => 'tehran', 'is_active' => true]);
        ReferralProposal::query()->create(['case_id' => $case->id, 'clinic_id' => $clinic->id, 'proposed_by_user_id' => User::factory()->create(['role' => 'coordinator'])->id, 'status' => 'proposed', 'reasoning' => 'synthetic demo', 'source_language' => 'fa', 'proposed_at' => now()->subDay()]);
        config()->set('royadarman.panel_demo_access', true);
        $this->actingAs($patient)->withSession(['panel_demo' => true, 'panel_demo_user_id' => (string) $patient->id])->get('/en/panel/cases/'.$case->id)->assertOk()
            ->assertDontSee('id="upload-form"', false)->assertDontSee('/documents/'.$document->id.'/content', false)
            ->assertDontSee('data-referral-decision=', false)->assertSee(__('panel.demo_notice'));
    }

    public function test_all_locales_show_real_report_content_without_translation_or_diagnosis_invention(): void
    {
        [$patient, $case] = $this->patientCase();
        $this->review($case, $this->document($case), 1, 'متن گزارش ثبت‌شده');
        foreach (['fa', 'ar', 'en'] as $locale) {
            $this->page($patient, $case, $locale)->assertOk()->assertSee('متن گزارش ثبت‌شده')
                ->assertSee(__('patient_case.file_check_note'))->assertDontSee('patient_case.file_check_note')
                ->assertSee(__('patient_case.review_help'))->assertDontSee('patient_case.review_help');
        }
    }

    public function test_missing_narrative_and_metadata_are_explicitly_unavailable(): void
    {
        [$patient, $case] = $this->patientCase();
        $data = $this->page($patient, $case)->viewData();
        $data['reviews'] = collect([(object) ['id' => 'synthetic-projection', 'revision_number' => null, 'source_language' => null, 'signed_at' => 'invalid-date', 'published_at' => null, 'image_adequacy' => '', 'observations' => ' ', 'limitations' => null, 'options' => null, 'recommended_next_step' => null]]);
        $html = view('panel.case', $data)->render();
        $this->assertStringContainsString(e(__('patient_case.not_recorded')), $html);
        $this->assertStringContainsString(e(__('patient_case.date_unknown')), $html);
        $this->assertStringContainsString(e(__('patient_case.language_unknown')), $html);
        $this->assertStringNotContainsString('datetime="invalid-date"', $html);
    }

    private function patientCase(): array
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = PatientCase::query()->create(['public_reference' => 'UI-'.Str::upper(Str::random(10)), 'patient_user_id' => $patient->id, 'service_type' => 'opg_review', 'status' => 'clinician_review', 'patient_mobile' => '09120000000', 'patient_mobile_hash' => hash('sha256', Str::random()), 'budget_band' => 'balanced', 'source_language' => 'fa', 'version' => 1]);

        return [$patient, $case];
    }

    private function document(PatientCase $case, DocumentStatus $status = DocumentStatus::Approved, string $name = 'opg.png'): ClinicalDocument
    {
        $policy = PolicyVersion::query()->create(['policy_key' => 'opg_document_sharing', 'version' => 'ui-'.Str::ulid(), 'locale' => 'fa', 'content' => 'synthetic consent', 'content_hash' => hash('sha256', 'synthetic consent'), 'published_at' => now()->subDay()]);
        $consent = ConsentEvent::query()->create(['subject_user_id' => $case->patient_user_id, 'case_id' => $case->id, 'policy_version_id' => $policy->id, 'purpose' => 'opg_document_sharing', 'decision' => 'accepted', 'locale' => 'fa', 'channel' => 'web', 'ip_hash' => hash('sha256', 'synthetic-ip'), 'user_agent_hash' => hash('sha256', 'synthetic-agent'), 'created_at' => now()->subDay()]);

        return ClinicalDocument::query()->create(['case_id' => $case->id, 'uploaded_by_user_id' => $case->patient_user_id, 'consent_event_id' => $consent->id, 'original_name' => $name, 'storage_disk' => 'private-opg', 'storage_key' => 'PRIVATE-STORAGE-'.Str::ulid(), 'detected_mime' => 'image/png', 'byte_size' => 68, 'sha256' => hash('sha256', $name), 'status' => $status]);
    }

    private function review(PatientCase $case, ClinicalDocument $document, int $revision, string $observations, bool $published = true, bool $signed = true): ReviewRevision
    {
        $clinician = User::factory()->create(['role' => 'clinician']);
        $review = ReviewRevision::query()->create(['case_id' => $case->id, 'clinical_document_id' => $document->id, 'clinician_user_id' => $clinician->id, 'revision_number' => $revision, 'source_language' => 'fa', 'image_adequacy' => 'Recorded image adequacy', 'observations' => $observations, 'limitations' => 'Recorded limitations', 'options' => 'Recorded options', 'recommended_next_step' => 'Recorded next step', 'signed_at' => $signed ? now()->subHours(2) : null]);
        if ($published) {
            $review->publicationEvents()->create(['actor_user_id' => $clinician->id, 'event' => 'published', 'created_at' => now()->subHour()]);
        }

        return $review;
    }

    private function page(User $patient, PatientCase $case, string $locale = 'en'): TestResponse
    {
        return $this->actingAs($patient)->get('/'.$locale.'/panel/cases/'.$case->id);
    }
}
