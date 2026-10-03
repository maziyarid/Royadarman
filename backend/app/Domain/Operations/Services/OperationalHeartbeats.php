<?php

namespace App\Domain\Operations\Services;

use App\Jobs\RecordOperationalHeartbeat;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

/** Local execution evidence, not process liveness or a cross-host cache. */
final class OperationalHeartbeats
{
    public const QUEUES = ['otp', 'scanning', 'notifications', 'maintenance'];

    public const MAX_AGE_SECONDS = 300;

    private const MAX_BYTES = 4096;

    public function __construct(private readonly ?string $directory = null) {}

    public function snapshot(): array
    {
        $now = now()->timestamp;
        try {
            $active = $this->active();
        } catch (\Throwable) {
            $active = false;
        }
        $unknown = ['state' => 'unobservable', 'age_seconds' => null];
        $scheduler = $active ? $this->evidence('scheduler', $now) : $unknown;
        $queues = [];
        foreach (self::QUEUES as $queue) {
            $queues[$queue] = $active ? $this->evidence($queue, $now) : $unknown;
        }
        $states = array_column([$scheduler, ...array_values($queues)], 'state');

        return [
            'state' => in_array('unobservable', $states, true) ? 'unknown' : (in_array('stale', $states, true) ? 'degraded' : 'ok'),
            'scheduler' => $scheduler,
            'queues' => $queues,
            'observed_at' => now()->utc()->toIso8601String(),
        ];
    }

    public function activate(): void
    {
        $this->locked(fn () => $this->write('activation', ['version' => 1, 'active' => true]));
    }

    /** Disable first. Refuse reserved/mismatched rows and roll back every deletion. */
    public function retire(): void
    {
        $this->locked(function (): void {
            $this->write('activation', ['version' => 1, 'active' => false]);
            foreach (self::QUEUES as $queue) {
                $this->recover($queue);
            }
            $connection = $this->database();
            $connection->transaction(function () use ($connection): void {
                $jobs = $connection->table(config('queue.connections.database.table', 'jobs'));
                foreach (self::QUEUES as $queue) {
                    $pending = $this->read('pending-'.$queue);
                    if ($pending === null) {
                        continue;
                    }
                    if (! $this->validPending($pending)) {
                        throw new RuntimeException('Invalid heartbeat pending record.');
                    }
                    $row = (clone $jobs)->where('id', $pending['job_id'])->lockForUpdate()->first();
                    if ($row === null) {
                        continue;
                    }
                    $this->assertOwnProbe($row, $queue, $pending);
                    if ($row->reserved_at !== null) {
                        throw new RuntimeException('Heartbeat probe remains reserved.');
                    }
                    (clone $jobs)->where('id', $row->id)->whereNull('reserved_at')->delete();
                }
            });
        });
    }

    /** One bounded pending probe per queue; only expired, unreserved OWN jobs are retired. */
    public function tick(): void
    {
        $this->locked(function (): void {
            if (! $this->active()) {
                return;
            }
            $issuedAt = now()->timestamp;
            foreach (self::QUEUES as $queue) {
                $this->dispatchProbe($queue, $issuedAt);
            }
            // Failure never renews or erases earlier execution. It ages out after 300s.
            $this->recordUnlocked('scheduler', $issuedAt);
        });
    }

    public function recordQueue(string $queue, int $issuedAt, string $probeId): void
    {
        $this->assertQueue($queue);
        $this->locked(function () use ($queue, $issuedAt, $probeId): void {
            if (! $this->active()) {
                return;
            }
            $this->recover($queue);
            $pending = $this->read('pending-'.$queue);
            if (! $this->validPending($pending) || $pending['probe_id'] !== $probeId || $pending['issued_at'] !== $issuedAt) {
                return;
            }
            // Never turn an old/replayed probe into current health at handling time.
            if ($issuedAt > now()->timestamp || now()->timestamp - $issuedAt > self::MAX_AGE_SECONDS) {
                return;
            }
            $this->recordUnlocked($queue, $issuedAt);
        });
    }

    private function dispatchProbe(string $queue, int $issuedAt): void
    {
        $this->recover($queue);
        $pending = $this->read('pending-'.$queue);
        if ($pending !== null && ! $this->validPending($pending)) {
            throw new RuntimeException('Invalid heartbeat pending record.');
        }
        if ($pending !== null && $pending['issued_at'] > $issuedAt) {
            throw new RuntimeException('Future heartbeat pending record.');
        }
        $connection = $this->database();
        $next = $connection->transaction(function () use ($connection, $queue, $pending, $issuedAt): ?array {
            $jobs = $connection->table(config('queue.connections.database.table', 'jobs'));
            if ($pending !== null) {
                $row = (clone $jobs)->where('id', $pending['job_id'])->lockForUpdate()->first();
                if ($row !== null) {
                    $this->assertOwnProbe($row, $queue, $pending);
                    if ($row->reserved_at !== null) {
                        return null; // Never steal/delete an in-flight reservation, even an expired probe.
                    }
                    if ($issuedAt - $pending['issued_at'] <= self::MAX_AGE_SECONDS) {
                        return null;
                    }
                    $this->write('journal-'.$queue, ['version' => 1, 'prior' => $pending, 'next' => null]);
                    (clone $jobs)->where('id', $row->id)->whereNull('reserved_at')->delete();
                }
            }
            // A durable journal precedes any push, even when there is no prior row.
            $this->write('journal-'.$queue, ['version' => 1, 'prior' => $pending, 'next' => null]);
            $probeId = bin2hex(random_bytes(16));
            $jobId = Queue::connection('database')->push(new RecordOperationalHeartbeat($queue, $issuedAt, $probeId), '', $queue);
            if (! is_int($jobId) && ! (is_string($jobId) && ctype_digit($jobId))) {
                throw new RuntimeException('Heartbeat queue did not persist its probe.');
            }
            $next = ['version' => 1, 'probe_id' => $probeId, 'issued_at' => $issuedAt, 'job_id' => (int) $jobId];
            $this->write('journal-'.$queue, ['version' => 1, 'prior' => $pending, 'next' => $next]);

            return $next;
        });
        if ($next !== null) {
            // SQL and files are separate stores. Keep both identities until recovery
            // verifies actual committed rows; a lost commit response must not orphan prior.
            $this->write('pending-'.$queue, $next);
        }
    }

    private function recover(string $queue): void
    {
        $journal = $this->read('journal-'.$queue);
        if ($journal === null) {
            return;
        }
        if (array_keys($journal) !== ['version', 'prior', 'next'] || $journal['version'] !== 1
            || ($journal['prior'] !== null && ! $this->validPending($journal['prior']))
            || ($journal['next'] !== null && ! $this->validPending($journal['next']))) {
            throw new RuntimeException('Invalid heartbeat recovery journal.');
        }
        $connection = $this->database();
        $survivors = $connection->transaction(function () use ($connection, $queue, $journal): array {
            $survivors = [];
            foreach (['prior', 'next'] as $key) {
                $pending = $journal[$key];
                if ($pending === null) {
                    continue;
                }
                $row = $connection->table(config('queue.connections.database.table', 'jobs'))
                    ->where('id', $pending['job_id'])->lockForUpdate()->first();
                if ($row !== null) {
                    $this->assertOwnProbe($row, $queue, $pending);
                    $survivors[] = $pending;
                }
            }

            return $survivors;
        });
        if (count($survivors) > 1) {
            throw new RuntimeException('Ambiguous heartbeat recovery identities.');
        }
        $pending = $survivors[0] ?? $journal['next'] ?? $journal['prior'];
        if ($pending !== null) {
            $this->write('pending-'.$queue, $pending);
        } else {
            $this->remove('pending-'.$queue);
        }
        $this->remove('journal-'.$queue);
    }

    private function remove(string $name): void
    {
        $path = $this->path($name);
        if (file_exists($path) && ! @unlink($path)) {
            throw new RuntimeException('Heartbeat storage removal failed.');
        }
    }

    private function active(): bool
    {
        $record = $this->read('activation');
        if ($record === null) {
            return false;
        }
        if (array_keys($record) !== ['version', 'active'] || $record['version'] !== 1 || ! is_bool($record['active'])) {
            throw new RuntimeException('Invalid heartbeat activation record.');
        }

        return $record['active'];
    }

    private function database(): Connection
    {
        if (config('queue.connections.database.driver') !== 'database') {
            throw new RuntimeException('Heartbeat database queue is unavailable.');
        }

        $connection = DB::connection(config('queue.connections.database.connection'));
        if ($connection->transactionLevel() !== 0) {
            throw new RuntimeException('Heartbeat requires its own database transaction boundary.');
        }

        return $connection;
    }

    private function assertOwnProbe(object $row, string $queue, array $pending): void
    {
        $payload = strlen($row->payload) <= 16384 ? json_decode($row->payload, true) : null;
        $identity = '"probeId";s:32:"'.$pending['probe_id'].'"';
        if ($row->queue !== $queue || ! is_array($payload)
            || ($payload['displayName'] ?? null) !== RecordOperationalHeartbeat::class
            || ! is_string($payload['data']['command'] ?? null)
            || ! str_contains($payload['data']['command'], $identity)) {
            throw new RuntimeException('Heartbeat pending job identity mismatch.');
        }
    }

    private function validPending(?array $record): bool
    {
        return $record !== null && array_keys($record) === ['version', 'probe_id', 'issued_at', 'job_id']
            && $record['version'] === 1 && is_string($record['probe_id']) && preg_match('/^[a-f0-9]{32}$/D', $record['probe_id']) === 1
            && is_int($record['issued_at']) && $record['issued_at'] > 0 && is_int($record['job_id']) && $record['job_id'] > 0;
    }

    private function recordUnlocked(string $component, int $issuedAt): void
    {
        if ($issuedAt <= 0 || $issuedAt > now()->timestamp) {
            throw new RuntimeException('Invalid heartbeat timestamp.');
        }
        $previous = $this->read($component);
        if ($previous !== null && (! $this->validEvidence($previous) || $previous['issued_at'] > now()->timestamp)) {
            throw new RuntimeException('Invalid heartbeat evidence.');
        }
        if ($previous !== null && $previous['issued_at'] >= $issuedAt) {
            return;
        }
        $this->write($component, ['version' => 1, 'issued_at' => $issuedAt]);
    }

    private function evidence(string $component, int $now): array
    {
        try {
            $record = $this->read($component);
            if (! $this->validEvidence($record) || $record['issued_at'] > $now) {
                return ['state' => 'unobservable', 'age_seconds' => null];
            }
            $age = $now - $record['issued_at'];

            return ['state' => $age <= self::MAX_AGE_SECONDS ? 'recent' : 'stale', 'age_seconds' => $age];
        } catch (\Throwable) {
            return ['state' => 'unobservable', 'age_seconds' => null];
        }
    }

    private function validEvidence(?array $record): bool
    {
        return $record !== null && array_keys($record) === ['version', 'issued_at'] && $record['version'] === 1
            && is_int($record['issued_at']) && $record['issued_at'] > 0;
    }

    private function assertQueue(string $queue): void
    {
        if (! in_array($queue, self::QUEUES, true)) {
            throw new RuntimeException('Unsupported heartbeat queue.');
        }
    }

    private function root(): string
    {
        $root = $this->directory ?? storage_path('app/private/operational-heartbeats');
        for ($path = $root; $path !== dirname($path); $path = dirname($path)) {
            if (is_link($path)) {
                throw new RuntimeException('Unsafe heartbeat directory.');
            }
        }
        if (! is_dir($root) && ! @mkdir($root, 0700, true) && ! is_dir($root)) {
            throw new RuntimeException('Heartbeat storage is unavailable.');
        }
        if (! @chmod($root, 0700)) {
            throw new RuntimeException('Heartbeat directory permissions failed.');
        }

        return $root;
    }

    private function path(string $name): string
    {
        if (! in_array($name, ['activation', 'scheduler', ...self::QUEUES, ...array_map(fn ($queue) => 'pending-'.$queue, self::QUEUES), ...array_map(fn ($queue) => 'journal-'.$queue, self::QUEUES)], true)) {
            throw new RuntimeException('Unsupported heartbeat record.');
        }
        $path = $this->root().'/'.$name.'.json';
        if (is_link($path) || (file_exists($path) && ! is_file($path))) {
            throw new RuntimeException('Unsafe heartbeat record.');
        }

        return $path;
    }

    private function read(string $name): ?array
    {
        $path = $this->path($name);
        if (! file_exists($path)) {
            return null;
        }
        if ((fileperms($path) & 0777) !== 0600) {
            throw new RuntimeException('Heartbeat record permissions failed.');
        }
        $json = @file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);
        if (! is_string($json) || strlen($json) > self::MAX_BYTES) {
            throw new RuntimeException('Heartbeat record is unreadable.');
        }
        $record = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        if (! is_array($record)) {
            throw new RuntimeException('Invalid heartbeat record.');
        }

        return $record;
    }

    private function write(string $name, array $record): void
    {
        $path = $this->path($name);
        $temporary = $this->root().'/.heartbeat-'.bin2hex(random_bytes(16));
        $handle = @fopen($temporary, 'x');
        if ($handle === false) {
            throw new RuntimeException('Heartbeat storage write failed.');
        }
        try {
            if (! @chmod($temporary, 0600)) {
                throw new RuntimeException('Heartbeat record permissions failed.');
            }
            $json = json_encode($record, JSON_THROW_ON_ERROR);
            if (fwrite($handle, $json) !== strlen($json) || ! fflush($handle) || ! fsync($handle) || ! @rename($temporary, $path)) {
                throw new RuntimeException('Heartbeat storage write failed.');
            }
        } finally {
            fclose($handle);
            if (file_exists($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function locked(callable $operation): void
    {
        $path = $this->root().'/.lock';
        if (is_link($path) || (file_exists($path) && ! is_file($path))) {
            throw new RuntimeException('Unsafe heartbeat lock.');
        }
        $handle = @fopen($path, 'c');
        if ($handle === false) {
            throw new RuntimeException('Heartbeat lock is unavailable.');
        }
        try {
            if (! @chmod($path, 0600)) {
                throw new RuntimeException('Heartbeat lock is unavailable.');
            }
            $deadline = microtime(true) + 1;
            while (! flock($handle, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Heartbeat lock is unavailable.');
                }
                usleep(20000);
            }
            $operation();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
