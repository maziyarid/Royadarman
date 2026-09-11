<?php

namespace App\Console\Commands;

use App\Domain\Identity\Enums\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            $smsProvider = (string) config('royadarman.sms.provider');
            if ($smsProvider === 'tsms') {
                foreach (['username', 'password', 'from'] as $key) {
                    if (trim((string) config('royadarman.sms.tsms.'.$key)) === '') {
                        $failures[] = 'INTAKE_ENABLED is true but TSMS '.strtoupper($key).' is not configured.';
                    }
                }
                $endpoint = (string) config('royadarman.sms.tsms.endpoint');
                $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
                $path = (string) parse_url($endpoint, PHP_URL_PATH);
                $scheme = strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));
                if (! in_array($scheme, ['http', 'https'], true)
                    || ! in_array($host, ['tsms.ir', 'www.tsms.ir'], true)
                    || $path !== '/url/tsmshttp.php') {
                    $failures[] = 'INTAKE_ENABLED is true but TSMS_API_URL is not the allowed TSMS URL API endpoint.';
                }
                if (config('royadarman.sms.callbacks_enabled') === true) {
                    $failures[] = 'TSMS callback delivery is not enabled in this integration; set ROYADARMAN_SMS_CALLBACKS_ENABLED=false.';
                }
            } elseif ($smsProvider === 'http') {
                if (empty(config('royadarman.sms.endpoint')) || empty(config('royadarman.sms.token'))) {
                    $failures[] = 'INTAKE_ENABLED is true but generic SMS endpoint/token are not configured.';
                }
            } else {
                $failures[] = 'INTAKE_ENABLED is true but ROYADARMAN_SMS_PROVIDER is unsupported.';
            }

            if (config('royadarman.sms.callbacks_enabled') === true && empty(config('royadarman.sms.callback_secret'))) {
                $failures[] = 'SMS callbacks are enabled but ROYADARMAN_SMS_CALLBACK_SECRET is not configured.';
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

            $this->checkOperationalStaffing($failures);
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

    /** @param list<string> $failures */
    private function checkOperationalStaffing(array &$failures): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('practitioners')) {
            $failures[] = 'INTAKE_ENABLED is true but operational staff tables are not available; run migrations before launch.';

            return;
        }

        $hasCoordinator = DB::table('users')
            ->where('role', UserRole::Coordinator->value)
            ->where('is_active', true)
            ->exists();
        if (! $hasCoordinator) {
            $failures[] = 'INTAKE_ENABLED is true but no active coordinator is provisioned.';
        }

        $hasClinicalLead = DB::table('users')
            ->join('practitioners', 'practitioners.user_id', '=', 'users.id')
            ->where('users.role', UserRole::Clinician->value)
            ->where('users.is_active', true)
            ->where('practitioners.credential_status', 'verified')
            ->where(fn ($query) => $query->whereNull('practitioners.expires_at')->orWhere('practitioners.expires_at', '>', now()))
            ->exists();
        if (! $hasClinicalLead) {
            $failures[] = 'INTAKE_ENABLED is true but no active verified non-expired clinician is provisioned.';
        }
    }
}
