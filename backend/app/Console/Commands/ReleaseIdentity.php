<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class ReleaseIdentity extends Command
{
    protected $signature = 'royadarman:release-identity {--write}';

    protected $description = 'Emit or write the deployed release identity (commit, build time, composer.lock hash) for traceability.';

    public function handle(): int
    {
        $commit = $this->resolveCommit();
        $build = now()->toIso8601String();
        $lockPath = base_path('composer.lock');
        $lockHash = File::exists($lockPath) ? hash_file('sha256', $lockPath) : null;
        $lockModified = File::exists($lockPath) ? date('c', File::lastModified($lockPath)) : null;

        $identity = [
            'commit' => $commit,
            'built_at' => $build,
            'composer_lock_sha256' => $lockHash,
            'composer_lock_modified_at' => $lockModified,
        ];

        if ($this->option('write')) {
            $path = storage_path('app/release-identity.json');
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($identity, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
            $this->info("Release identity written to {$path}.");
        }

        $this->line(json_encode($identity, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    private function resolveCommit(): ?string
    {
        $envCommit = env('ROYADARMAN_RELEASE_COMMIT');
        if (! is_string($envCommit) || $envCommit === '') {
            $process = getenv('ROYADARMAN_RELEASE_COMMIT');
            $envCommit = is_string($process) ? $process : null;
        }
        if (is_string($envCommit) && $envCommit !== '') {
            return $envCommit;
        }
        $gitPaths = [base_path('.git'), dirname(base_path()).DIRECTORY_SEPARATOR.'.git'];
        foreach ($gitPaths as $path) {
            if (! File::exists($path.DIRECTORY_SEPARATOR.'HEAD')) {
                continue;
            }
            $head = trim((string) File::get($path.DIRECTORY_SEPARATOR.'HEAD'));
            if (str_starts_with($head, 'ref:')) {
                $refPath = $path.DIRECTORY_SEPARATOR.trim(substr($head, 4));
                if (File::exists($refPath)) {
                    return trim(File::get($refPath));
                }
            } elseif (preg_match('/^[0-9a-f]{40}$/', $head)) {
                return $head;
            }
        }

        return null;
    }
}
