<?php

namespace Tests\Feature;

use App\Domain\Cases\Enums\CaseStatus;
use App\Jobs\ScanClinicalDocument;
use App\Models\ClinicalDocument;
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

class DocumentConsentTest extends TestCase
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

    private function makeConsent(User $patient, PatientCase $case, string $policyKey = 'opg_document_sharing', string $locale = 'fa', bool $revoked = false): ConsentEvent
    {
        $policy = PolicyVersion::query()->create([
            'policy_key' => $policyKey,
            'version' => 'approved-1',
            'locale' => $locale,
            'content' => 'consent text',
            'content_hash' => hash('sha256', 'consent text'),
            'published_at' => now(),
        ]);

        return ConsentEvent::query()->create([
            'subject_user_id' => $patient->id,
            'case_id' => $case->id,
            'policy_version_id' => $policy->id,
            'purpose' => 'opg_document_sharing',
            'decision' => 'accepted',
            'locale' => $locale,
            'channel' => 'web',
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent_hash' => hash('sha256', 'test'),
            'created_at' => now(),
            'revoked_at' => $revoked ? now() : null,
        ]);
    }

    public function test_upload_without_consent_is_rejected(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $upload = UploadedFile::fake()->createWithContent('opg.png', base64_decode(self::ONE_PIXEL_PNG, true));

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/documents", ['document' => $upload])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'document.consent_required');
        $this->assertDatabaseCount('clinical_documents', 0);
    }

    public function test_upload_with_valid_consent_succeeds(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $consent = $this->makeConsent($patient, $case);
        $upload = UploadedFile::fake()->createWithContent('opg.png', base64_decode(self::ONE_PIXEL_PNG, true));

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/documents", ['document' => $upload])
            ->assertAccepted();

        $document = ClinicalDocument::query()->first();
        $this->assertSame($consent->id, $document->consent_event_id);
    }

    public function test_upload_with_revoked_consent_is_rejected(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $this->makeConsent($patient, $case, 'opg_document_sharing', 'fa', true);
        $upload = UploadedFile::fake()->createWithContent('opg.png', base64_decode(self::ONE_PIXEL_PNG, true));

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/documents", ['document' => $upload])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'document.consent_required');
    }

    public function test_consent_for_wrong_patient_does_not_authorise(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $other = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $otherCase = $this->makeCase($other);
        $this->makeConsent($other, $otherCase);
        $upload = UploadedFile::fake()->createWithContent('opg.png', base64_decode(self::ONE_PIXEL_PNG, true));

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/documents", ['document' => $upload])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'document.consent_required');
    }

    public function test_consent_for_wrong_policy_does_not_authorise(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $this->makeConsent($patient, $case, 'case_coordination');
        $upload = UploadedFile::fake()->createWithContent('opg.png', base64_decode(self::ONE_PIXEL_PNG, true));

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/documents", ['document' => $upload])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'document.consent_required');
    }

    public function test_another_patient_cannot_upload_to_someone_elses_case(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $intruder = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $this->makeConsent($patient, $case);
        $upload = UploadedFile::fake()->createWithContent('opg.png', base64_decode(self::ONE_PIXEL_PNG, true));

        $this->actingAs($intruder)
            ->postJson("/api/v1/cases/{$case->id}/documents", ['document' => $upload])
            ->assertNotFound();
    }

    public function test_renamed_executable_is_rejected_before_storage(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $this->makeConsent($patient, $case);

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/documents", [
                'document' => UploadedFile::fake()->createWithContent('not-an-opg.jpg', '<?php echo "unsafe";'),
            ])
            ->assertUnprocessable();
        $this->assertSame([], Storage::disk('opg-quarantine')->allFiles());
    }

    public function test_oversized_file_is_rejected(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $this->makeConsent($patient, $case);

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/documents", [
                'document' => UploadedFile::fake()->create('huge.png', 21 * 1024),
            ])
            ->assertUnprocessable();
    }

    public function test_pdf_extension_is_rejected(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $this->makeConsent($patient, $case);

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/documents", [
                'document' => UploadedFile::fake()->createWithContent('scan.pdf', '%PDF-1.4 fake'),
            ])
            ->assertUnprocessable();
    }

    public function test_zero_byte_file_is_rejected(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $this->makeConsent($patient, $case);

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/documents", [
                'document' => UploadedFile::fake()->createWithContent('empty.png', ''),
            ])
            ->assertUnprocessable();
    }

    public function test_document_limit_enforced(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient);
        $this->makeConsent($patient, $case);

        for ($i = 0; $i < 3; $i++) {
            $upload = UploadedFile::fake()->createWithContent("opg{$i}.png", base64_decode(self::ONE_PIXEL_PNG, true));
            $this->actingAs($patient)
                ->postJson("/api/v1/cases/{$case->id}/documents", ['document' => $upload])
                ->assertAccepted();
        }

        $fourth = UploadedFile::fake()->createWithContent('opg3.png', base64_decode(self::ONE_PIXEL_PNG, true));
        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/documents", ['document' => $fourth])
            ->assertUnprocessable();
    }
}
