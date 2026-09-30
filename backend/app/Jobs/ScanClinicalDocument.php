<?php

namespace App\Jobs;

use App\Domain\Documents\Contracts\DocumentScanner;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\AuditEvent;
use App\Models\ClinicalDocument;
use App\Models\ScanAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ScanClinicalDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public readonly string $documentId)
    {
        $this->onQueue('scanning');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("document-scan:{$this->documentId}"))->expireAfter(300)];
    }

    public function handle(DocumentScanner $scanner): void
    {
        /** @var ClinicalDocument $document */
        $document = ClinicalDocument::query()->findOrFail($this->documentId);

        if (! in_array($document->status, [DocumentStatus::Quarantined, DocumentStatus::Scanning], true)) {
            return;
        }

        $document->forceFill([
            'status' => DocumentStatus::Scanning,
            'scan_attempted_at' => now(),
            'scan_error_code' => null,
        ])->save();

        $attemptNumber = ScanAttempt::query()->where('document_id', $document->id)->count() + 1;
        $attempt = ScanAttempt::query()->firstOrCreate(
            ['document_id' => $document->id, 'attempt_number' => $attemptNumber],
            ['status' => 'running', 'file_hash' => $document->sha256, 'started_at' => now()]
        );

        $quarantine = Storage::disk($document->storage_disk);
        $path = $quarantine->path($document->storage_key);
        if (! hash_equals($document->sha256, hash_file('sha256', $path))) {
            $attempt->update(['status' => 'failed', 'error_code' => 'hash_mismatch', 'finished_at' => now()]);
            throw new RuntimeException('Document hash changed while quarantined.');
        }
        $result = $scanner->scan($path);

        if (! $result->clean) {
            $this->complete($document, DocumentStatus::Rejected, $result->engine, $result->reference, 'infected');
            $attempt->update(['status' => 'rejected', 'engine' => $result->engine, 'finished_at' => now()]);
            $quarantine->delete($document->storage_key);

            return;
        }

        $approvedDisk = (string) config('royadarman.opg.disk', 'private-opg');
        $source = $quarantine->readStream($document->storage_key);

        if ($source === null || $source === false) {
            throw new RuntimeException('Unable to read quarantined document for promotion.');
        }

        try {
            Storage::disk($approvedDisk)->writeStream($document->storage_key, $source);
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
        }

        $approvedPath = Storage::disk($approvedDisk)->path($document->storage_key);
        if (! hash_equals($document->sha256, hash_file('sha256', $approvedPath))) {
            Storage::disk($approvedDisk)->delete($document->storage_key);
            $attempt->update(['status' => 'failed', 'error_code' => 'promotion_hash_mismatch', 'finished_at' => now()]);
            throw new RuntimeException('Document hash changed during promotion.');
        }

        DB::transaction(function () use ($document, $approvedDisk, $result): void {
            $retentionDays = config('royadarman.retention.document_days');
            $hasRetention = is_numeric($retentionDays) && (int) $retentionDays > 0;
            $document->forceFill([
                'storage_disk' => $approvedDisk,
                'status' => DocumentStatus::Approved,
                'scan_provider' => $result->engine,
                'scan_reference' => $result->reference,
                'scan_result' => ['verdict' => 'clean'],
                'scan_completed_at' => now(),
                'approved_at' => now(),
                'retention_until' => $hasRetention ? now()->addDays((int) $retentionDays) : null,
            ])->save();

            if ($hasRetention) {
                DB::table('retention_jobs')->insertOrIgnore([
                    'id' => (string) Str::ulid(),
                    'resource_type' => ClinicalDocument::class,
                    'resource_id' => $document->id,
                    'status' => 'pending',
                    'execute_after' => $document->retention_until,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->audit($document, 'success', 'clean');
        });

        $quarantine->delete($document->storage_key);
        $attempt->update(['status' => 'approved', 'engine' => $result->engine, 'finished_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        $document = ClinicalDocument::query()->find($this->documentId);

        if ($document === null || $document->status === DocumentStatus::Deleted) {
            return;
        }

        $document->forceFill([
            'status' => DocumentStatus::ScanFailed,
            'scan_error_code' => class_basename($exception),
            'scan_completed_at' => now(),
        ])->save();

        ScanAttempt::query()->where('document_id', $document->id)->whereNull('finished_at')->update(['status' => 'failed', 'error_code' => class_basename($exception), 'finished_at' => now()]);

        $this->audit($document, 'failed', 'scanner_unavailable');
    }

    private function complete(ClinicalDocument $document, DocumentStatus $status, string $provider, string $reference, string $verdict): void
    {
        DB::transaction(function () use ($document, $status, $provider, $reference, $verdict): void {
            $document->forceFill([
                'status' => $status,
                'scan_provider' => $provider,
                'scan_reference' => $reference,
                'scan_result' => ['verdict' => $verdict],
                'scan_completed_at' => now(),
            ])->save();

            $this->audit($document, $status === DocumentStatus::Rejected ? 'denied' : 'success', $verdict);
        });
    }

    private function audit(ClinicalDocument $document, string $result, string $verdict): void
    {
        AuditEvent::query()->create([
            'action' => 'document.scanned',
            'resource_type' => ClinicalDocument::class,
            'resource_id' => $document->id,
            'result' => $result,
            'context' => ['verdict' => $verdict],
            'correlation_id' => (string) Str::ulid(),
            'created_at' => now(),
        ]);
    }
}
