<?php

namespace App\Domain\Operations\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Redacted, count-only operational evidence for launch readiness.
 *
 * It reports BACKLOG evidence (stale queue rows, failed jobs, stuck outbox
 * work, failed deliveries). It cannot prove that a queue worker process is
 * alive: worker_liveness is always "unobservable" until a scheduler-written
 * heartbeat exists (shared console/config change, needs a handoff).
 * No payloads, recipients, references or ids are ever returned.
 */
final class OperationalHealth
{
    public const BACKLOG_AGE_SECONDS = 300;

    public const STUCK_MINUTES = 15;

    /**
     * @return array{state:string,reasons:list<string>,signals:array<string,int>,worker_liveness:string}
     */
    public function snapshot(?CarbonInterface $now = null): array
    {
        $now ??= now();

        try {
            $signals = $this->signals($now);
        } catch (Throwable) {
            return ['state' => 'unknown', 'reasons' => ['database_unavailable'], 'signals' => [], 'worker_liveness' => 'unobservable'];
        }

        $reasons = [];
        if ($signals['failed_jobs'] > 0) {
            $reasons[] = 'failed_jobs';
        }
        if ($signals['oldest_queued_age_seconds'] > self::BACKLOG_AGE_SECONDS) {
            $reasons[] = 'queue_backlog_age';
        }
        if ($signals['stale_reserved_jobs'] > 0) {
            $reasons[] = 'stale_reserved_jobs';
        }
        if ($signals['stuck_outbox'] > 0) {
            $reasons[] = 'stuck_outbox';
        }
        if ($signals['stuck_sending_deliveries'] > 0) {
            $reasons[] = 'stuck_sending_deliveries';
        }
        if ($signals['failed_deliveries_24h'] > 0) {
            $reasons[] = 'failed_deliveries_recent';
        }

        return [
            'state' => $reasons === [] ? 'ok' : 'degraded',
            'reasons' => $reasons,
            'signals' => $signals,
            'worker_liveness' => 'unobservable',
        ];
    }

    /**
     * @return array<string,int>
     */
    private function signals(CarbonInterface $now): array
    {
        $timestamp = $now->getTimestamp();
        $cutoff = $now->toImmutable()->subMinutes(self::STUCK_MINUTES);
        $since = $now->toImmutable()->subDay();
        $retryAfter = (int) config('queue.connections.database.retry_after', 90);
        $jobs = DB::connection(config('queue.connections.database.connection'))
            ->table(config('queue.connections.database.table', 'jobs'));
        $failedJobs = DB::connection(config('queue.failed.database'))
            ->table(config('queue.failed.table', 'failed_jobs'));
        $oldest = (clone $jobs)->whereNull('reserved_at')->min('available_at');

        return [
            'queued_jobs' => (clone $jobs)->count(),
            'oldest_queued_age_seconds' => $oldest === null ? 0 : max(0, $timestamp - (int) $oldest),
            'stale_reserved_jobs' => (clone $jobs)
                ->whereNotNull('reserved_at')
                ->where('reserved_at', '<=', $timestamp - (2 * $retryAfter))
                ->count(),
            'failed_jobs' => $failedJobs->count(),
            'pending_outbox' => DB::table('outbox_events')->whereNull('processed_at')->count(),
            'stuck_outbox' => DB::table('outbox_events')
                ->whereNull('processed_at')
                ->where('available_at', '<=', $cutoff)
                ->count(),
            'stuck_sending_deliveries' => DB::table('notification_deliveries')
                ->where('status', 'sending')
                ->where(function ($query) use ($cutoff): void {
                    $query->where('updated_at', '<=', $cutoff)
                        ->orWhere(fn ($inner) => $inner->whereNull('updated_at')->where('created_at', '<=', $cutoff));
                })
                ->count(),
            'failed_deliveries_24h' => DB::table('notification_deliveries')
                ->where('status', 'failed')
                ->where(function ($query) use ($since): void {
                    $query->where('updated_at', '>=', $since)
                        ->orWhere(fn ($inner) => $inner->whereNull('updated_at')->where('created_at', '>=', $since));
                })
                ->count(),
        ];
    }
}
