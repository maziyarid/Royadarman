<?php

namespace Tests\Feature;

use App\Domain\Cases\Enums\CaseStatus;
use App\Jobs\ScanClinicalDocument;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsentAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private const ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('royadarman.intake_enabled', true);
        Storage::fake('opg-quarantine');
        Queue::fake([ScanClinicalDocument::class]);
    }

    private function makeCase(User $patient): PatientCase
    {
        return PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'opg_review',
            'status' => CaseStatus::Submitted,
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => 1,
        ]);
    }

    private function publishPolicy(string $key = 'opg_document_sharing', string $locale = 'fa', string $version = 'approved-1'): PolicyVersion
    {
        return PolicyVersion::query()->create([
            'policy_key' => $key,
            'version' => $version,
            'locale' => $locale,
            'content' => 'consent text '.$version,
            'content_hash' => hash('sha256', 'consent text '.$version),
            'published_at' => now(),
        ]);
    }

    public function test_patient_can_accept_opg_consent_via_real_http_path(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $policy = $this->publishPolicy();

        $response = $this->actingAs($patient)
            ->withHeaders(['Accept-Language' => 'fa'])
            ->postJson("/api/v1/cases/{$case->id}/consent/opg_document_sharing", [
                'policy_version' => $policy->version,
                'content_hash' => $policy->content_hash,
                'locale' => 'fa',
            ])
            ->assertCreated()
            ->assertJsonPath('data.purpose', 'opg_document_sharing')
            ->assertJsonPath('data.policy_version', $policy->version);

        $this->assertDatabaseHas('consent_events', [
            'subject_user_id' => $patient->id,
            'case_id' => $case->id,
            'purpose' => 'opg_document_sharing',
            'decision' => 'accepted',
        ]);
    }

    public function test_accepted_opg_consent_enables_real_upload_via_http(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $policy = $this->publishPolicy();

        $this->actingAs($patient)
            ->withHeaders(['Accept-Language' => 'fa'])
            ->postJson("/api/v1/cases/{$case->id}/consent/opg_document_sharing", [
                'policy_version' => $policy->version,
                'content_hash' => $policy->content_hash,
                'locale' => 'fa',
            ])
            ->assertCreated();

        $upload = UploadedFile::fake()->createWithContent('opg.png', base64_decode(self::ONE_PIXEL_PNG, true));

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/documents", ['document' => $upload])
            ->assertAccepted();

        $this->assertDatabaseCount('clinical_documents', 1);
    }

    public function test_consent_with_substituted_newer_policy_version_is_rejected(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $shown = $this->publishPolicy('opg_document_sharing', 'fa', 'v1');
        $newer = $this->publishPolicy('opg_document_sharing', 'fa', 'v2');

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/consent/opg_document_sharing", [
                'policy_version' => $shown->version,
                'content_hash' => $newer->content_hash,
                'locale' => 'fa',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'consent.policy_mismatch');

        $this->assertDatabaseMissing('consent_events', ['subject_user_id' => $patient->id, 'purpose' => 'opg_document_sharing']);
    }

    public function test_consent_with_unpublished_policy_version_rejected(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $policy = PolicyVersion::query()->create([
            'policy_key' => 'opg_document_sharing',
            'version' => 'draft-1',
            'locale' => 'fa',
            'content' => 'draft text',
            'content_hash' => hash('sha256', 'draft text'),
            'published_at' => null,
        ]);

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/consent/opg_document_sharing", [
                'policy_version' => $policy->version,
                'content_hash' => $policy->content_hash,
                'locale' => 'fa',
            ])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'error.consent.translation_unavailable');
    }

    public function test_consent_for_wrong_locale_rejected(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $policy = $this->publishPolicy('opg_document_sharing', 'en', 'approved-1');

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/consent/opg_document_sharing", [
                'policy_version' => $policy->version,
                'content_hash' => $policy->content_hash,
                'locale' => 'fa',
            ])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'error.consent.translation_unavailable');
    }

    public function test_another_patient_cannot_accept_consent_for_someone_elses_case(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $intruder = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $policy = $this->publishPolicy();

        $this->actingAs($intruder)
            ->postJson("/api/v1/cases/{$case->id}/consent/opg_document_sharing", [
                'policy_version' => $policy->version,
                'content_hash' => $policy->content_hash,
                'locale' => 'fa',
            ])
            ->assertNotFound();
    }

    public function test_patient_can_revoke_opg_consent_and_future_uploads_fail(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $policy = $this->publishPolicy();

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/consent/opg_document_sharing", [
                'policy_version' => $policy->version,
                'content_hash' => $policy->content_hash,
                'locale' => 'fa',
            ])
            ->assertCreated();

        $this->actingAs($patient)
            ->deleteJson("/api/v1/cases/{$case->id}/consent/opg_document_sharing")
            ->assertOk()
            ->assertJsonPath('data.revoked_events.0', function ($value): bool {
                return $value !== null;
            });

        $this->assertDatabaseHas('consent_events', [
            'subject_user_id' => $patient->id,
            'case_id' => $case->id,
            'purpose' => 'opg_document_sharing',
        ]);
        $this->assertNotNull(ConsentEvent::query()->where('subject_user_id', $patient->id)->where('purpose', 'opg_document_sharing')->value('revoked_at'));

        $upload = UploadedFile::fake()->createWithContent('opg.png', base64_decode(self::ONE_PIXEL_PNG, true));
        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/documents", ['document' => $upload])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'document.consent_required');
    }

    public function test_revoke_without_active_consent_returns_not_found(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);

        $this->actingAs($patient)
            ->deleteJson("/api/v1/cases/{$case->id}/consent/opg_document_sharing")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'consent.no_active_consent');
    }

    public function test_unsupported_purpose_rejected(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/consent/bogus_purpose", [
                'policy_version' => 'approved-1',
                'content_hash' => hash('sha256', 'x'),
                'locale' => 'fa',
            ])
            ->assertNotFound();
    }
}
