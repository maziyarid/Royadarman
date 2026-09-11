<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class Preflight extends Command
{
    protected $signature = 'royadarman:preflight';

    protected $description = 'Refuse unsafe production configuration before deployment or release.';

    public function handle(): int
    {
        $env = (string) config('app.env');
        $isProduction = $env === 'production';
        $failures = [];

        if ($isProduction) {
            if (empty(config('app.key'))) {
                $failures[] = 'APP_KEY is missing or empty in production.';
            }
            if (config('app.debug') === true) {
                $failures[] = 'APP_DEBUG is true in production.';
            }
        }

        $phoneHashKey = config('royadarman.phone_hash_key');
        if (! is_string($phoneHashKey) || trim($phoneHashKey) === '') {
            $failures[] = 'ROYADARMAN_PHONE_HASH_KEY is missing or empty; identities cannot be hashed safely.';
        } else {
            $appKey = (string) config('app.key');
            if ($appKey !== '' && hash_equals($appKey, $phoneHashKey)) {
                $failures[] = 'ROYADARMAN_PHONE_HASH_KEY must not equal APP_KEY; it is an independent lookup secret.';
            }
            if (strlen($phoneHashKey) < 32) {
                $failures[] = 'ROYADARMAN_PHONE_HASH_KEY is shorter than 32 characters; use a high-entropy secret (e.g. 64 hex chars).';
            }
        }

        if (config('royadarman.intake_enabled') === true) {
            if (empty(config('royadarman.sms.endpoint')) || empty(config('royadarman.sms.token'))) {
                $failures[] = 'INTAKE_ENABLED is true but SMS endpoint/token are not configured.';
            }
            if (empty(config('royadarman.sms.callback_secret'))) {
                $failures[] = 'INTAKE_ENABLED is true but SMS callback secret is not configured.';
            }
            if (config('royadarman.opg.scanner.enabled') === false) {
                $failures[] = 'INTAKE_ENABLED is true but the OPG scanner is disabled (must fail closed).';
            }
            if (empty(config('royadarman.opg.scanner.command'))) {
                $failures[] = 'INTAKE_ENABLED is true but the scanner command is empty.';
            }
            $retention = config('royadarman.retention.document_days');
            if (! is_numeric($retention) || (int) $retention <= 0) {
                $failures[] = 'INTAKE_ENABLED is true but ROYADARMAN_DOCUMENT_RETENTION_DAYS is not a positive integer.';
            }
            $grantTtl = config('royadarman.referral.grant_ttl_minutes');
            if (! is_numeric($grantTtl) || (int) $grantTtl <= 0) {
                $failures[] = 'INTAKE_ENABLED is true but ROYADARMAN_REFERRAL_GRANT_TTL_MINUTES is not a positive integer; referral grants must be time-limited.';
            }
        }

        $disk = (string) config('filesystems.default');
        if ($isProduction && in_array($disk, ['public', 's3-public'], true)) {
            $failures[] = "Default filesystem disk '{$disk}' is publicly accessible in production.";
        }

        $queue = (string) config('queue.default');
        if ($isProduction && $queue === 'sync') {
            $failures[] = 'QUEUE_CONNECTION is sync in production; database or a real queue is required.';
        }

        if (config('session.encrypt') === false && $isProduction) {
            $failures[] = 'SESSION_ENCRYPT is false in production.';
        }

        if ($failures !== []) {
            $this->error('Preflight FAILED with the following issues:');
            foreach ($failures as $failure) {
                $this->error(' - '.$failure);
            }

            return self::FAILURE;
        }

        $this->info('Preflight OK: configuration passes the safety gates.');

        return self::SUCCESS;
    }
}
