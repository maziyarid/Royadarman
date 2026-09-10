<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Jobs\ScanClinicalDocument;
use App\Models\AuditEvent;
use App\Models\ClinicalDocument;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class QuarantineClinicalDocument
{
    /** @var array<string, list<string>> */
    private const MIME_BY_EXTENSION = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];

    public function handle(PatientCase $case, User $uploader, UploadedFile $file, ?ConsentEvent $consentEvent = null): ClinicalDocument
    {
        if ($case->documents()->whereNotIn('status', [DocumentStatus::Deleted->value, DocumentStatus::Rejected->value])->count() >= 3) {
            throw ValidationException::withMessages(['document' => __('ui.errors.document_limit')]);
        }
        [$extension, $detectedMime] = $this->validateFile($file);
        $disk = (string) config('royadarman.opg.quarantine_disk', 'opg-quarantine');
        $key = "cases/{$case->id}/".(string) Str::ulid().'.upload';

        $stream = fopen($file->getRealPath(), 'rb');

        if ($stream === false) {
            throw ValidationException::withMessages(['opg' => __('ui.errors.document_unreadable')]);
        }

        try {
            Storage::disk($disk)->writeStream($key, $stream);
        } finally {
            fclose($stream);
        }

        try {
            $document = DB::transaction(function () use ($case, $uploader, $file, $disk, $key, $detectedMime, $extension, $consentEvent): ClinicalDocument {
                $document = ClinicalDocument::query()->create([
                    'case_id' => $case->id,
                    'uploaded_by_user_id' => $uploader->id,
                    'consent_event_id' => $consentEvent?->id,
                    'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
                    'storage_disk' => $disk,
                    'storage_key' => $key,
                    'detected_mime' => $detectedMime,
                    'byte_size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'status' => DocumentStatus::Quarantined,
                ]);

                AuditEvent::query()->create([
                    'actor_user_id' => $uploader->id,
                    'action' => 'document.quarantined',
                    'resource_type' => ClinicalDocument::class,
                    'resource_id' => $document->id,
                    'result' => 'success',
                    'context' => ['mime' => $detectedMime, 'extension' => $extension, 'bytes' => $file->getSize()],
                    'correlation_id' => (string) Str::ulid(),
                    'created_at' => now(),
                ]);

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($key);
            throw $exception;
        }

        ScanClinicalDocument::dispatch($document->id)->afterCommit();

        return $document;
    }

    /** @return array{string, string} */
    private function validateFile(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['opg' => __('ui.errors.document_incomplete')]);
        }

        $size = $file->getSize();
        $maxBytes = (int) config('royadarman.opg.max_kilobytes', 15 * 1024) * 1024;

        if ($size === false || $size < 1 || $size > $maxBytes) {
            throw ValidationException::withMessages(['opg' => __('ui.errors.document_too_large')]);
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (! array_key_exists($extension, self::MIME_BY_EXTENSION)) {
            throw ValidationException::withMessages(['opg' => __('ui.errors.document_extension')]);
        }

        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());

        if (! is_string($detectedMime) || ! in_array($detectedMime, self::MIME_BY_EXTENSION[$extension], true)) {
            throw ValidationException::withMessages(['opg' => __('ui.errors.document_mime_mismatch')]);
        }

        $dimensions = getimagesize($file->getRealPath());
        $maxPixels = (int) config('royadarman.opg.max_image_pixels', 60_000_000);

        if ($dimensions === false || ($dimensions[0] * $dimensions[1]) > $maxPixels) {
            throw ValidationException::withMessages(['opg' => __('ui.errors.image_dimensions')]);
        }

        return [$extension, $detectedMime];
    }
}
