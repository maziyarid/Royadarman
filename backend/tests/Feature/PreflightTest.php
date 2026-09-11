<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_preflight_passes_when_intake_enabled_with_all_tsms_gates(): void
    {
        $this->configureSafeTsmsIntake();

        $this->artisan('royadarman:preflight')->assertSuccessful();
    }

    private function configureSafeTsmsIntake(): void
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
    }
}
