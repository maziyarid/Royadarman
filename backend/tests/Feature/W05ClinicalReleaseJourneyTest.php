<?php

namespace Tests\Feature;

use App\Domain\Documents\Contracts\DocumentScanner;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\ValueObjects\ScanResult;
use App\Jobs\ScanClinicalDocument;
use App\Models\ClinicalDocument;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReviewRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class W05ClinicalReleaseJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const IMAGE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config(['royadarman.intake_enabled' => true]);
        Queue::fake();
        Mail::fake();
        Http::preventStrayRequests();
        Storage::fake('opg-quarantine');
        Storage::fake('private-opg');
    }

    public function test_no_opg_request_persists_and_an_existing_patient_session_can_return_without_reonboarding(): void
    {
        [$patient, $case] = $this->submittedRequest();
        $this->assertDatabaseCount('patient_cases', 1);
        $this->assertDatabaseCount('clinical_documents', 0);
        $this->assertDatabaseCount('review_revisions', 0);

        // This exercises an existing authenticated session, not OTP delivery or login.
        foreach (range(1, 2) as $visit) {
            $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)
                ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
                ->assertViewHas('documents', fn ($documents) => $documents->isEmpty())
                ->assertViewHas('reviews', fn ($reviews) => $reviews->isEmpty());
        }
        $this->actingAs(User::factory()->create(['role' => 'patient']))
            ->get('/en/panel/cases/'.$case->id)->assertNotFound();
        $this->assertDatabaseCount('patient_cases', 1);
    }

    public function test_actual_upload_scan_assignment_draft_release_reaches_only_the_owning_patient(): void
    {
        [$patient, $case, $coordinator, $clinician] = $this->submittedRequest();
        $document = $this->upload($patient, $case);
        $this->assertSame(DocumentStatus::Quarantined, $document->status);
        $this->actingAs($patient)->getJson($this->documentUrl($case, $document).'/content')->assertNotFound();
        $this->assertDatabaseCount('document_access_events', 0);
        Queue::assertPushed(ScanClinicalDocument::class, fn ($job) => $job->documentId === $document->id);

        $this->approve($document);
        $this->assign($case, $coordinator, $clinician);
        $bytes = $this->actingAs($clinician)->get($this->documentUrl($case, $document).'/content')
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(base64_decode(self::IMAGE, true), $bytes->streamedContent());
        $review = $this->draft($case, $document, $clinician);
        $this->assertNull($review->signed_at);
        $this->assertDatabaseCount('publication_events', 0);
        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk()->assertDontSee($review->observations);
        $this->actingAs($clinician)->get('/en/panel/cases/'.$case->id)->assertOk()->assertSee($review->observations);

        $this->actingAs($clinician)->postJson($this->publishUrl($case, $review))
            ->assertOk()->assertJsonPath('data.published', true);
        $review->refresh();
        $this->assertNotNull($review->signed_at);
        $this->assertDatabaseHas('publication_events', [
            'review_revision_id' => $review->id,
            'actor_user_id' => $clinician->id,
            'event' => 'published',
        ]);
        foreach (range(1, 2) as $visit) {
            $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk()
                ->assertSee($review->observations)->assertSee($review->limitations)
                ->assertDontSee($document->storage_key)
                ->assertViewHas('reviews', fn ($reviews) => $reviews->sole()->id === $review->id);
        }
        foreach (['patient', 'owner', 'tech_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/en/panel/cases/'.$case->id)->assertNotFound()->assertDontSee($review->observations);
        }
        $this->actingAs($clinician)->postJson($this->publishUrl($case, $review))
            ->assertConflict()->assertJsonPath('error.code', 'review.already_published');
        $this->assertDatabaseCount('publication_events', 1);
        Mail::assertNothingSent();
    }

    #[DataProvider('nonApprovedStates')]
    public function test_own_patient_can_poll_nonapproved_status_without_a_file_byte_grant(string $status, int $contentStatus): void
    {
        [$patient, $case] = $this->submittedRequest();
        $document = $this->upload($patient, $case);
        // State fixtures isolate status-endpoint policy; scanner transitions have their own test.
        $document->update(['status' => $status]);
        foreach (['patient', 'owner', 'coordinator'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson($this->documentUrl($case, $document))->assertNotFound();
        }
        $this->actingAs($patient)->getJson($this->documentUrl($case, $document).'/content')->assertStatus($contentStatus);
        $this->assertDatabaseCount('document_access_events', 0);
        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk()
            ->assertViewHas('documents', fn ($documents) => $documents->sole()->status === $status);

        // Metadata polling must not demand the separate permission to stream approved bytes.
        $this->getJson($this->documentUrl($case, $document))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.id', $document->id)->assertJsonPath('data.status', $status)
            ->assertJsonMissingPath('data.storage_key')->assertJsonMissingPath('data.original_name');
    }

    public static function nonApprovedStates(): array
    {
        return [
            'quarantined' => ['quarantined', 404],
            'scanning' => ['scanning', 404],
            'rejected' => ['rejected', 403],
            'scanner failed' => ['scan_failed', 503],
        ];
    }

    public function test_assigned_verified_reviewer_can_learn_scan_status_but_cannot_read_unscanned_bytes(): void
    {
        [$patient, $case, $coordinator, $clinician] = $this->submittedRequest();
        $document = $this->upload($patient, $case);
        $this->assign($case, $coordinator, $clinician);
        $this->actingAs($clinician)->getJson($this->documentUrl($case, $document).'/content')->assertNotFound();
        $this->assertDatabaseCount('document_access_events', 0);
        $this->getJson($this->documentUrl($case, $document))->assertOk()->assertJsonPath('data.status', 'quarantined');
    }

    public function test_scanner_outage_retains_quarantine_and_never_promotes_or_releases_a_report(): void
    {
        [$patient, $case] = $this->submittedRequest();
        $document = $this->upload($patient, $case);
        $job = new ScanClinicalDocument($document->id);
        $scanner = new class implements DocumentScanner
        {
            public function scan(string $absolutePath): ScanResult
            {
                throw new RuntimeException('SYNTHETIC-W05-SCANNER-OUTAGE');
            }
        };
        try {
            $job->handle($scanner);
            $this->fail('The synthetic scanner outage must propagate to the queue failure handler.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SYNTHETIC-W05-SCANNER-OUTAGE', $exception->getMessage());
            $job->failed($exception);
        }
        $this->assertSame(DocumentStatus::ScanFailed, $document->fresh()->status);
        Storage::disk('opg-quarantine')->assertExists($document->storage_key);
        $this->assertSame([], Storage::disk('private-opg')->allFiles());
        $this->actingAs($patient)->getJson($this->documentUrl($case, $document).'/content')
            ->assertServiceUnavailable()->assertJsonPath('error.code', 'document.scan_failed');
        $this->assertDatabaseCount('publication_events', 0);
    }

    public function test_mislabeled_upload_is_rejected_by_the_real_upload_route_before_document_creation(): void
    {
        [$patient, $case] = $this->submittedRequest();
        $this->acceptDocumentConsent($patient, $case);
        $this->actingAs($patient)->post('/api/v1/cases/'.$case->id.'/documents', [
            'document' => UploadedFile::fake()->createWithContent('not-an-image.png', '<?php echo "SYNTHETIC-UNSAFE";'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('clinical_documents', 0);
        $this->assertSame([], Storage::disk('opg-quarantine')->allFiles());
        Queue::assertNotPushed(ScanClinicalDocument::class);
    }

    public function test_actual_consent_withdrawal_after_draft_prevents_signing_and_staff_byte_access(): void
    {
        [$patient, $case, $coordinator, $clinician] = $this->submittedRequest();
        $document = $this->upload($patient, $case);
        $this->approve($document);
        $this->assign($case, $coordinator, $clinician);
        $review = $this->draft($case, $document, $clinician);
        $this->actingAs($patient)->deleteJson('/api/v1/cases/'.$case->id.'/consent/opg_document_sharing')->assertOk();
        $this->actingAs($clinician)->postJson($this->publishUrl($case, $review))
            ->assertForbidden()->assertJsonPath('error.code', 'review.consent_revoked');
        $this->getJson($this->documentUrl($case, $document).'/content')->assertNotFound();
        $this->assertNull($review->fresh()->signed_at);
        $this->assertDatabaseCount('publication_events', 0);
        $this->actingAs($patient)->get('/en/panel/cases/'.$case->id)->assertOk()->assertDontSee($review->observations);
    }

    #[DataProvider('revokedReviewerStates')]
    public function test_polling_and_bytes_recheck_current_reviewer_eligibility(string $state): void
    {
        [$patient, $case, $coordinator, $clinician] = $this->submittedRequest();
        $document = $this->upload($patient, $case);
        $this->approve($document);
        $this->assign($case, $coordinator, $clinician);
        $this->actingAs($clinician)->getJson($this->documentUrl($case, $document))
            ->assertOk()->assertJsonPath('data.status', 'approved');

        // Only the consent change has an existing patient-facing revocation route.
        // Other fixtures represent a persisted administrative change between requests.
        match ($state) {
            'assignment_released' => DB::table('case_assignments')->where('case_id', $case->id)
                ->where('assignee_user_id', $clinician->id)->update(['released_at' => now()]),
            'credential_expired' => DB::table('practitioners')->where('user_id', $clinician->id)
                ->update(['expires_at' => now()->subSecond()]),
            'credential_unverified' => DB::table('practitioners')->where('user_id', $clinician->id)
                ->update(['credential_status' => 'pending']),
            'consent_revoked' => $this->actingAs($patient)->deleteJson('/api/v1/cases/'.$case->id.'/consent/opg_document_sharing')->assertOk(),
            'source_deleted' => $document->update(['deleted_at' => now()]),
        };
        $this->actingAs($clinician)->getJson($this->documentUrl($case, $document))->assertNotFound();
        $this->getJson($this->documentUrl($case, $document).'/content')->assertNotFound();
        $this->assertDatabaseCount('document_access_events', 0);
        $this->assertDatabaseCount('publication_events', 0);
    }

    public static function revokedReviewerStates(): array
    {
        return [
            'assignment released' => ['assignment_released'],
            'credential expired' => ['credential_expired'],
            'credential no longer verified' => ['credential_unverified'],
            'consent withdrawn' => ['consent_revoked'],
            'source deleted' => ['source_deleted'],
        ];
    }

    public function test_patient_status_is_case_bound_and_deleted_source_remains_hidden(): void
    {
        [$patient, $case] = $this->submittedRequest();
        $document = $this->upload($patient, $case);
        $this->approve($document);
        $this->actingAs($patient)->getJson($this->documentUrl($case, $document))->assertOk();
        $differentCase = $case->replicate();
        $differentCase->public_reference = 'RD-W05-'.Str::random(6);
        $differentCase->save();
        $this->getJson($this->documentUrl($differentCase, $document))->assertNotFound();
        $this->getJson($this->documentUrl($differentCase, $document).'/content')->assertNotFound();
        $document->update(['deleted_at' => now()]);
        $this->getJson($this->documentUrl($case, $document))->assertNotFound();
        $this->getJson($this->documentUrl($case, $document).'/content')->assertNotFound();
        $this->assertDatabaseCount('document_access_events', 0);
    }

    /** @return array{User, PatientCase, User, User} */
    private function submittedRequest(): array
    {
        $patient = User::factory()->create(['role' => 'patient', 'locale' => 'fa', 'phone' => '09120000000', 'phone_hash' => hash('sha256', Str::uuid())]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);
        $clinician = User::factory()->create(['role' => 'clinician', 'is_active' => true]);
        DB::table('practitioners')->insert([
            'id' => (string) Str::ulid(), 'user_id' => $clinician->id,
            'licence_number' => encrypt('SYNTHETIC-W05-'.$clinician->id),
            'licence_hash' => hash('sha256', 'SYNTHETIC-W05-'.$clinician->id),
            'credential_status' => 'verified', 'verified_at' => now()->subDay(), 'expires_at' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $policy = $this->policy('case_coordination');
        $created = $this->actingAs($patient)->postJson('/api/v1/cases/draft', [
            'service_type' => 'opg_review', 'name' => 'SYNTHETIC-W05-PATIENT',
            'budget_band' => 'call', 'budget_input_unit' => 'toman', 'source_language' => 'fa',
        ], ['Idempotency-Key' => 'w05-draft-'.Str::uuid()])->assertCreated();
        $case = PatientCase::query()->findOrFail($created->json('data.id'));
        $body = ['version' => $case->version, 'policy_version' => $policy->version, 'content_hash' => $policy->content_hash];
        $key = 'w05-submit-'.Str::uuid();
        $this->postJson('/api/v1/cases/'.$case->id.'/submit', $body, ['Idempotency-Key' => $key])->assertOk();
        $this->postJson('/api/v1/cases/'.$case->id.'/submit', $body, ['Idempotency-Key' => $key])->assertOk();
        $this->assertDatabaseCount('consent_events', 1);
        $this->assertDatabaseCount('case_assignments', 1);

        return [$patient, $case->fresh(), $coordinator, $clinician];
    }

    private function policy(string $purpose): PolicyVersion
    {
        return PolicyVersion::query()->create([
            'policy_key' => $purpose, 'version' => 'w05-'.Str::ulid(), 'locale' => 'fa',
            'content' => 'SYNTHETIC TEST POLICY - NOT AN APPROVED LIVE POLICY',
            'content_hash' => hash('sha256', 'SYNTHETIC TEST POLICY - NOT AN APPROVED LIVE POLICY'), 'published_at' => now(),
        ]);
    }

    private function acceptDocumentConsent(User $patient, PatientCase $case): void
    {
        $policy = $this->policy('opg_document_sharing');
        $this->actingAs($patient)->postJson('/api/v1/cases/'.$case->id.'/consent/opg_document_sharing', [
            'policy_version' => $policy->version, 'content_hash' => $policy->content_hash, 'locale' => 'fa',
        ])->assertCreated();
    }

    private function upload(User $patient, PatientCase $case): ClinicalDocument
    {
        $this->acceptDocumentConsent($patient, $case);
        $response = $this->actingAs($patient)->post('/api/v1/cases/'.$case->id.'/documents', [
            'document' => UploadedFile::fake()->createWithContent('SYNTHETIC-W05.png', base64_decode(self::IMAGE, true)),
        ], ['Accept' => 'application/json'])->assertStatus(202)->assertJsonPath('data.status', 'quarantined');

        return ClinicalDocument::query()->findOrFail($response->json('data.id'));
    }

    private function approve(ClinicalDocument $document): void
    {
        $scanner = new class implements DocumentScanner
        {
            public function scan(string $absolutePath): ScanResult
            {
                return new ScanResult(true, 'synthetic-w05-scanner', hash_file('sha256', $absolutePath));
            }
        };
        (new ScanClinicalDocument($document->id))->handle($scanner);
        $document->refresh();
        $this->assertSame(DocumentStatus::Approved, $document->status);
        $this->assertSame('private-opg', $document->storage_disk);
        $this->assertSame(hash('sha256', base64_decode(self::IMAGE, true)), $document->sha256);
        Storage::disk('private-opg')->assertExists($document->storage_key);
        Storage::disk('opg-quarantine')->assertMissing($document->storage_key);
    }

    private function assign(PatientCase $case, User $coordinator, User $clinician): void
    {
        $this->actingAs($coordinator)->postJson('/api/v1/staff/cases/'.$case->id.'/assignments', [
            'assignee_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'version' => $case->fresh()->version,
        ])->assertOk();
    }

    private function draft(PatientCase $case, ClinicalDocument $document, User $clinician): ReviewRevision
    {
        $response = $this->actingAs($clinician)->postJson('/api/v1/staff/cases/'.$case->id.'/reviews', [
            'source_language' => 'fa', 'clinical_document_id' => $document->id,
            'image_adequacy' => 'SYNTHETIC image-quality assessment', 'observations' => 'SYNTHETIC-W05-RELEASED-NARRATIVE',
            'limitations' => 'SYNTHETIC: image review does not replace examination',
            'options' => 'SYNTHETIC options', 'recommended_next_step' => 'SYNTHETIC next step',
        ])->assertCreated();

        return ReviewRevision::query()->findOrFail($response->json('data.id'));
    }

    private function documentUrl(PatientCase $case, ClinicalDocument $document): string
    {
        return '/api/v1/cases/'.$case->id.'/documents/'.$document->id;
    }

    private function publishUrl(PatientCase $case, ReviewRevision $review): string
    {
        return '/api/v1/staff/cases/'.$case->id.'/reviews/'.$review->id.'/publish';
    }
}
