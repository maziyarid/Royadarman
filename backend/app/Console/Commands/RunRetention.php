<?php

namespace App\Console\Commands;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\ClinicalDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class RunRetention extends Command
{
    protected $signature = 'retention:run {--limit=50}';
    protected $description = 'Execute approved, due retention jobs without inventing retention periods.';

    public function handle(): int
    {
        DB::table('retention_jobs')->where('status', 'pending')->where('execute_after', '<=', now())->orderBy('execute_after')->limit((int) $this->option('limit'))->pluck('id')->each(function (string $id): void {
            DB::transaction(function () use ($id): void {
                $job = DB::table('retention_jobs')->lockForUpdate()->where('id', $id)->first();
                if (! $job || $job->status !== 'pending' || $job->exception_reason) {
                    return;
                }
                if ($job->resource_type !== ClinicalDocument::class) {
                    DB::table('retention_jobs')->where('id', $id)->update(['status' => 'unsupported', 'updated_at' => now()]);
                    return;
                }
                $document = ClinicalDocument::query()->find($job->resource_id);
                if ($document) {
                    Storage::disk($document->storage_disk)->delete($document->storage_key);
                    $document->update(['status' => DocumentStatus::Deleted, 'deleted_at' => now()]);
                }
                DB::table('retention_jobs')->where('id', $id)->update(['status' => 'completed', 'updated_at' => now()]);
            });
        });
        return self::SUCCESS;
    }
}
