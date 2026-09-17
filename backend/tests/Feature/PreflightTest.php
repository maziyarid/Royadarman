<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PreflightTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_passes_with_valid_independent_key_and_intake_disabled(): void
    {
        config()->set('royadarman.phone_hash_key', str_repeat('a', 64));
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('royadarman.intake_enabled', false);

        $this->artisan('royadarman:preflight')->assertSuccessful();
    }

    public function test_preflight_fails_when_phone_hash_key_missing(): void
    {
        config()->set('royadarman.phone_hash_key', null);
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $this->artisan('royadarman:preflight')->assertFailed();
    }

    public function test_preflight_fails_when_phone_hash_key_empty(): void
    {
        config()->set('royadarman.phone_hash_key', '');
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $this->artisan('royadarman:preflight')->assertFailed();
    }

    public function test_preflight_fails_when_phone_hash_key_equals_app_key(): void
    {
        $shared = str_repeat('z', 64);
        config()->set('royadarman.phone_hash_key', $shared);
        config()->set('app.key', $shared);

        $this->artisan('royadarman:preflight')->assertFailed();
    }

    public function test_preflight_fails_when_phone_hash_key_too_short(): void
    {
        config()->set('royadarman.phone_hash_key', 'short');
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $this->artisan('royadarman:preflight')->assertFailed();
    }

    public function test_preflight_fails_when_intake_enabled_without_referral_ttl(): void
    {
        $this->configureSafeTsmsIntake();
        config()->set('royadarman.referral.grant_ttl_minutes', null);

        $this->artisan('royadarman:preflight')->assertFailed();
    }

    public function test_preflight_fails_when_intake_enabled_without_tsms_credentials(): void
    {
        $this->configureSafeTsmsIntake();
        config()->set('royadarman.sms.tsms.username', null);

        $this->artisan('royadarman:preflight')->assertFailed();
    }

    public function test_preflight_rejects_plain_http_tsms_endpoint(): void
    {
        $this->configureSafeTsmsIntake();
        config()->set('royadarman.sms.tsms.endpoint', 'http://tsms.ir/url/tsmshttp.php');

        $this->artisan('royadarman:preflight')->assertFailed();
    }

    public function test_preflight_rejects_plain_http_generic_sms_endpoint(): void
    {
        $this->configureSafeTsmsIntake();
        config()->set('royadarman.sms.provider', 'http');
        config()->set('royadarman.sms.endpoint', 'http://sms.example.test/send');
        config()->set('royadarman.sms.token', 'test-token');

        $this->artisan('royadarman:preflight')->assertFailed();
    }

    public function test_preflight_does_not_require_callback_secret_for_send_only_tsms(): void
    {
        $this->configureSafeTsmsIntake();
        config()->set('royadarman.sms.callbacks_enabled', false);
        config()->set('royadarman.sms.callback_secret', null);

        $this->artisan('royadarman:preflight')->assertSuccessful();
    }

    public function test_preflight_rejects_callbacks_enabled_for_current_tsms_adapter(): void
    {
        $this->configureSafeTsmsIntake();
        config()->set('royadarman.sms.callbacks_enabled', true);
        config()->set('royadarman.sms.callback_secret', 'test-secret');

        $this->artisan('royadarman:preflight')->assertFailed();
    }

    public function test_preflight_fails_without_active_coordinator(): void
    {
        $this->configureSafeTsmsIntake(false, true);

        $this->artisan('royadarman:preflight')->assertFailed();
    }

    public function test_preflight_fails_without_verified_clinician(): void
    {
        $this->configureSafeTsmsIntake(true, false);

        $this->artisan('royadarman:preflight')->assertFailed();
    }

    public function test_preflight_passes_when_intake_enabled_with_all_tsms_and_staffing_gates(): void
    {
        $this->configureSafeTsmsIntake();

        $this->artisan('royadarman:preflight')->assertSuccessful();
    }

    public function test_preflight_fails_in_production_when_session_connection_is_split_from_audit(): void
    {
        config()->set('royadarman.phone_hash_key', str_repeat('a', 64));
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('app.env', 'production');
        config()->set('app.debug', false);
        config()->set('session.encrypt', true);
        config()->set('queue.default', 'database');
        config()->set('filesystems.default', 'local');
        config()->set('royadarman.intake_enabled', false);
        config()->set('database.default', 'sqlite');
        config()->set('session.connection', 'session_store');

        $this->artisan('royadarman:preflight')
            ->expectsOutputToContain('SESSION_CONNECTION')
            ->assertFailed();
    }

    public function test_preflight_allows_split_session_connection_outside_production(): void
    {
        config()->set('royadarman.phone_hash_key', str_repeat('a', 64));
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('app.env', 'local');
        config()->set('royadarman.intake_enabled', false);
        config()->set('database.default', 'sqlite');
        config()->set('session.connection', 'session_store');

        $this->artisan('royadarman:preflight')->assertSuccessful();
    }

    private function configureSafeTsmsIntake(bool $withCoordinator = true, bool $withClinician = true): void
    {
        config()->set('royadarman.phone_hash_key', str_repeat('a', 64));
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('app.env', 'local');
        config()->set('royadarman.intake_enabled', true);
        config()->set('royadarman.sms.provider', 'tsms');
        config()->set('royadarman.sms.tsms.endpoint', 'https://tsms.ir/url/tsmshttp.php');
        config()->set('royadarman.sms.tsms.username', 'test-user');
        config()->set('royadarman.sms.tsms.password', 'test-password');
        config()->set('royadarman.sms.tsms.from', '30001234');
        config()->set('royadarman.sms.callbacks_enabled', false);
        config()->set('royadarman.sms.callback_secret', null);
        config()->set('royadarman.opg.scanner.enabled', true);
        config()->set('royadarman.opg.scanner.command', '/usr/bin/clamscan');
        config()->set('royadarman.retention.document_days', 30);
        config()->set('royadarman.referral.grant_ttl_minutes', 43200);

        if ($withCoordinator) {
            User::factory()->create(['role' => 'coordinator', 'is_active' => true]);
        }
        if ($withClinician) {
            $clinician = User::factory()->create(['role' => 'clinician', 'is_active' => true]);
            DB::table('practitioners')->insert([
                'id' => (string) Str::ulid(),
                'user_id' => $clinician->id,
                'licence_number' => encrypt('TEST-LICENCE'),
                'licence_hash' => hash('sha256', 'TEST-LICENCE-'.$clinician->id),
                'credential_status' => 'verified',
                'verified_at' => now(),
                'expires_at' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
