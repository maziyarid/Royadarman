<?php

namespace App\Console\Commands;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\AuditEvent;
use App\Models\ClinicalDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class RunRetention extends Command
{
    protected $signature = 'retention:run {--limit=50}';

    protected $description = 'Execute approved, due retention jobs without inventing retention periods.';

    public function handle(): int
    {
        DB::table('retention_jobs')
            ->where('status', 'pending')
            ->where('execute_after', '<=', now())
            ->orderBy('execute_after')
            ->limit((int) $this->option('limit'))
            ->pluck('id')
            ->each(function (string $id): void {
                DB::transaction(function () use ($id): void {
                    $job = DB::table('retention_jobs')->lockForUpdate()->where('id', $id)->first();
                    if (! $job || $job->status !== 'pending' || $job->exception_reason) {
                        return;
                    }
                    if ($job->resource_type !== ClinicalDocument::class) {
                        DB::table('retention_jobs')->where('id', $id)->update(['status' => 'unsupported', 'updated_at' => now()]);

                        return;
                    }

                    $document = ClinicalDocument::query()->lockForUpdate()->find($job->resource_id);

                    $disk = $document?->storage_disk;
                    $key = $document?->storage_key;

                    if ($document === null || $document->status === DocumentStatus::Deleted || $disk === null || $key === null) {
                        DB::table('retention_jobs')->where('id', $id)->update(['status' => 'completed', 'updated_at' => now()]);

                        return;
                    }

                    $correlationId = (string) Str::ulid();

                    try {
                        $deleted = Storage::disk($disk)->delete($key);
                    } catch (Throwable $exception) {
                        DB::table('retention_jobs')->where('id', $id)->update([
                            'status' => 'failed',
                            'exception_reason' => mb_substr($exception->getMessage(), 0, 1000),
                            'updated_at' => now(),
                        ]);
                        $this->audit($document, 'failed', 'storage_exception', $correlationId);

                        return;
                    }

                    if ($deleted !== true) {
                        DB::table('retention_jobs')->where('id', $id)->update([
                            'status' => 'failed',
                            'exception_reason' => 'storage_delete_returned_false',
                            'updated_at' => now(),
                        ]);
                        $this->audit($document, 'failed', 'storage_delete_failed', $correlationId);

                        return;
                    }

                    $document->update(['status' => DocumentStatus::Deleted, 'deleted_at' => now()]);
                    DB::table('retention_jobs')->where('id', $id)->update(['status' => 'completed', 'updated_at' => now()]);
                    $this->audit($document, 'success', 'retention_deleted', $correlationId);
                });
            });

        return self::SUCCESS;
    }

    private function audit(ClinicalDocument $document, string $result, string $verdict, string $correlationId): void
    {
        AuditEvent::query()->create([
            'action' => 'document.retention',
            'resource_type' => ClinicalDocument::class,
            'resource_id' => $document->id,
            'result' => $result,
            'context' => ['verdict' => $verdict],
            'correlation_id' => $correlationId,
            'created_at' => now(),
        ]);
    }
}
