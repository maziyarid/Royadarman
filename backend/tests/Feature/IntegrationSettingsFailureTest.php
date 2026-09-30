<?php

namespace Tests\Feature;

use App\Domain\Operations\Services\IntegrationSettings;
use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Synthetic data only. NOT RUN when authored: requires independent execution.
 * Values below are fake literals, not credentials.
 */
class IntegrationSettingsFailureTest extends TestCase
{
    use RefreshDatabase;

    private function corrupt(string $key): void
    {
        DB::table('integration_settings')->insert([
            'key' => $key, 'value' => 'this-is-not-valid-ciphertext', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function settings(): IntegrationSettings
    {
        return new IntegrationSettings;
    }

    public function test_unset_key_is_not_a_problem_and_uses_the_fallback(): void
    {
        $settings = $this->settings();

        $this->assertSame('env-value', $settings->value('sms_provider', 'env-value'));
        $this->assertSame(0, $settings->diagnostics()['problem_count']);
    }

    public function test_corrupted_value_is_reported_as_unreadable_without_leaking_anything(): void
    {
        Log::spy();
        $this->corrupt('sms_provider');
        $settings = $this->settings();

        $this->assertSame('tsms', $settings->value('sms_provider', 'tsms'));
        $diagnostics = $settings->diagnostics();

        $this->assertSame(['sms_provider' => 'unreadable'], $diagnostics['problems']);
        $this->assertStringNotContainsString('not-valid-ciphertext', json_encode($diagnostics, JSON_THROW_ON_ERROR));
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('integration_settings.unreadable', ['key' => 'sms_provider', 'reason' => 'DecryptException']);
    }

    public function test_unreadable_sms_provider_keeps_environment_provider_but_is_flagged(): void
    {
        config()->set('royadarman.sms.provider', 'tsms');
        $this->corrupt('sms_provider');
        $settings = $this->settings();

        $settings->applyToRuntimeConfig();

        // Documented availability fallback: the provider is NOT silently switched.
        $this->assertSame('tsms', config('royadarman.sms.provider'));
        $this->assertSame(1, $settings->diagnostics()['problem_count']);
    }

    public function test_unreadable_intake_flag_fails_closed_even_if_environment_enables_intake(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $this->corrupt('intake_enabled');

        $this->settings()->applyToRuntimeConfig();

        $this->assertFalse(config('royadarman.intake_enabled'));
    }

    public function test_direct_read_of_unreadable_intake_does_not_return_an_enabled_fallback(): void
    {
        $this->corrupt('intake_enabled');

        $this->assertFalse($this->settings()->value('intake_enabled', true));
    }

    public function test_unavailable_settings_store_closes_intake_without_disabling_existing_login(): void
    {
        config()->set('royadarman.intake_enabled', true);
        Schema::drop('integration_settings');
        $settings = $this->settings();

        $settings->applyToRuntimeConfig();

        $this->assertFalse(config('royadarman.intake_enabled'));
        $this->assertFalse($settings->value('intake_enabled', true));
        $this->assertSame('tsms', $settings->value('sms_provider', 'tsms'));
        $this->assertSame('unavailable', $settings->diagnostics()['database']);
        $this->assertSame('database_unavailable', $settings->forDisplay()['sms_provider']['problem']);
    }

    public function test_failure_to_log_does_not_break_safe_settings_fallback(): void
    {
        Log::shouldReceive('warning')->andThrow(new \RuntimeException('synthetic logging unavailable'));
        $this->corrupt('intake_enabled');

        $settings = $this->settings();
        $settings->applyToRuntimeConfig();

        $this->assertFalse(config('royadarman.intake_enabled'));
        $this->assertSame(['intake_enabled' => 'unreadable'], $settings->diagnostics()['problems']);
    }

    public function test_readable_override_still_applies(): void
    {
        config()->set('royadarman.intake_enabled', false);
        IntegrationSetting::query()->create(['key' => 'intake_enabled', 'value' => '1']);
        IntegrationSetting::query()->create(['key' => 'retention_document_days', 'value' => '90']);

        $settings = $this->settings();
        $settings->applyToRuntimeConfig();

        $this->assertTrue(config('royadarman.intake_enabled'));
        $this->assertSame(90, config('royadarman.retention.document_days'));
        $this->assertSame(0, $settings->diagnostics()['problem_count']);
    }

    public function test_non_numeric_integer_override_is_invalid_and_does_not_change_config(): void
    {
        config()->set('royadarman.retention.document_days', 30);
        IntegrationSetting::query()->create(['key' => 'retention_document_days', 'value' => 'abc']);
        $settings = $this->settings();

        $settings->applyToRuntimeConfig();

        $this->assertSame(30, config('royadarman.retention.document_days'));
        $this->assertSame(['retention_document_days' => 'invalid'], $settings->diagnostics()['problems']);
    }

    public function test_for_display_marks_unreadable_rows(): void
    {
        $this->corrupt('sms_callback_secret');

        $display = $this->settings()->forDisplay();

        $this->assertSame('unreadable', $display['sms_callback_secret']['problem']);
        $this->assertSame('', $display['sms_callback_secret']['value']);
        $this->assertTrue($display['sms_callback_secret']['has_override']);
        $this->assertNull($display['sms_provider']['problem']);
    }

    public function test_unreadable_override_display_distinguishes_fallback_from_missing_configuration(): void
    {
        config()->set('royadarman.sms.provider', 'tsms');
        config()->set('royadarman.intake_enabled', true);
        $this->corrupt('sms_provider');
        $this->corrupt('intake_enabled');
        $display = $this->settings()->forDisplay();

        $this->assertSame('environment', $display['sms_provider']['source']);
        $this->assertSame('tsms', $display['sms_provider']['value']);
        $this->assertSame('safety_fallback', $display['intake_enabled']['source']);
        $this->assertSame('0', $display['intake_enabled']['value']);
    }

    public function test_settings_page_exposes_a_redacted_warning_and_can_clear_unreadable_override(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->corrupt('sms_callback_secret');

        $this->actingAs($owner)->get('/en/panel/integrations')
            ->assertOk()
            ->assertSee(__('integrations.read_failure'))
            ->assertSee('name="clear[]" value="sms_callback_secret"', false)
            ->assertDontSee('this-is-not-valid-ciphertext');
    }

    public function test_launch_readiness_gate_fails_when_a_setting_is_unreadable(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->corrupt('tsms_password');

        $this->actingAs($owner)->get('/en/panel/launch-readiness')
            ->assertOk()
            ->assertViewHas('gates', fn (array $gates): bool => $gates['integration_settings']['ok'] === false);
    }
}
