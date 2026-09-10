<?php

namespace Tests\Feature;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\ServiceType;
use App\Domain\Documents\Contracts\DocumentScanner;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Services\QuarantineClinicalDocument;
use App\Domain\Documents\ValueObjects\ScanResult;
use App\Jobs\ScanClinicalDocument;
use App\Models\ClinicalDocument;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClinicalDocumentPipelineTest extends TestCase
{
    use RefreshDatabase;

    private const ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    public function test_valid_image_is_quarantined_and_scan_is_queued(): void
    {
        Queue::fake();
        Storage::fake('opg-quarantine');
        $user = User::factory()->create();
        $case = $this->patientCase();
        $upload = UploadedFile::fake()->createWithContent('sample-opg.png', base64_decode(self::ONE_PIXEL_PNG, true));

        $document = app(QuarantineClinicalDocument::class)->handle($case, $user, $upload);

        $this->assertSame(DocumentStatus::Quarantined, $document->status);
        Storage::disk('opg-quarantine')->assertExists($document->storage_key);
        Queue::assertPushed(ScanClinicalDocument::class, fn (ScanClinicalDocument $job): bool => $job->documentId === $document->id);
    }

    public function test_renamed_executable_is_rejected_before_storage(): void
    {
        Queue::fake();
        Storage::fake('opg-quarantine');
        $user = User::factory()->create();

        try {
            app(QuarantineClinicalDocument::class)->handle(
                $this->patientCase(),
                $user,
                UploadedFile::fake()->createWithContent('not-an-opg.jpg', '<?php echo "unsafe";'),
            );
            $this->fail('Expected validation to reject mismatched content.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('opg', $exception->errors());
            $this->assertSame([], Storage::disk('opg-quarantine')->allFiles());
            Queue::assertNothingPushed();
        }
    }

    public function test_clean_scan_promotes_document_to_private_disk(): void
    {
        Storage::fake('opg-quarantine');
        Storage::fake('private-opg');
        $document = ClinicalDocument::query()->create([
            'case_id' => $this->patientCase()->id,
            'original_name' => 'sample-opg.png',
            'storage_disk' => 'opg-quarantine',
            'storage_key' => 'cases/test/sample.upload',
            'detected_mime' => 'image/png',
            'byte_size' => 68,
            'sha256' => hash('sha256', 'image-bytes'),
            'status' => DocumentStatus::Quarantined,
        ]);
        Storage::disk('opg-quarantine')->put($document->storage_key, 'image-bytes');
        $scanner = new class implements DocumentScanner
        {
            public function scan(string $absolutePath): ScanResult
            {
                return new ScanResult(true, 'test-scanner', hash_file('sha256', $absolutePath));
            }
        };

        (new ScanClinicalDocument($document->id))->handle($scanner);

        $document->refresh();
        $this->assertSame(DocumentStatus::Approved, $document->status);
        $this->assertSame('private-opg', $document->storage_disk);
        Storage::disk('private-opg')->assertExists($document->storage_key);
        Storage::disk('opg-quarantine')->assertMissing($document->storage_key);
        $this->assertDatabaseHas('audit_events', ['resource_id' => $document->id, 'action' => 'document.scanned', 'result' => 'success']);
    }

    private function patientCase(): PatientCase
    {
        return PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(fake()->bothify('########')),
            'service_type' => ServiceType::OpgReview,
            'status' => CaseStatus::Submitted,
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', fake()->uuid()),
            'budget_band' => 'balanced',
        ]);
    }

    public function test_disabled_scanner_keeps_document_quarantined_fail_closed(): void
    {
        Storage::fake('opg-quarantine');
        config()->set('royadarman.opg.scanner.enabled', false);

        $document = ClinicalDocument::query()->create([
            'case_id' => $this->patientCase()->id,
            'original_name' => 'sample-opg.png',
            'storage_disk' => 'opg-quarantine',
            'storage_key' => 'cases/test/quarantined.upload',
            'detected_mime' => 'image/png',
            'byte_size' => 68,
            'sha256' => hash('sha256', 'image-bytes'),
            'status' => DocumentStatus::Quarantined,
        ]);
        Storage::disk('opg-quarantine')->put($document->storage_key, 'image-bytes');

        $scanner = app(DocumentScanner::class);

        try {
            (new ScanClinicalDocument($document->id))->handle($scanner);
            $this->fail('Expected scanner to throw when disabled.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('quarantined', $exception->getMessage());
        }

        // Scanner threw mid-handle; document stays in Scanning and the file
        // remains quarantined (never promoted to the approved disk). The queue
        // worker would invoke failed() to flip it to ScanFailed; here we prove
        // the fail-closed invariant: no promotion, no approval, file retained.
        $document->refresh();
        $this->assertNotSame(DocumentStatus::Approved, $document->status);
        Storage::disk('opg-quarantine')->assertExists($document->storage_key);

        // Simulate the queue worker's failed() callback to confirm the
        // fail-closed terminal status is applied.
        (new ScanClinicalDocument($document->id))->failed($exception);
        $document->refresh();
        $this->assertSame(DocumentStatus::ScanFailed, $document->status);
        $this->assertDatabaseHas('audit_events', [
            'resource_id' => $document->id,
            'action' => 'document.scanned',
            'result' => 'failed',
        ]);
    }
}
