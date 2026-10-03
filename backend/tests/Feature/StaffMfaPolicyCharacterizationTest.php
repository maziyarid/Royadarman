<?php

namespace Tests\Feature;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Services\OtpService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class StaffMfaCodeCapture implements OtpSender
{
    public string $code = '';

    public function send(string $mobile, string $code, string $locale): void
    {
        $this->code = $code;
    }
}

/**
 * CHARACTERIZATION of behaviour on main f99210e, not an endorsement of it.
 * Tests marked KNOWN GAP pin behaviour the owner may decide to change (PX.2
 * policy options A/B/C in issue #15). When the policy is decided, update the
 * marked tests deliberately. Synthetic data only. NOT RUN when authored.
 */
class StaffMfaPolicyCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'synthetic-Password-123';

    private function account(string $role, string $name, array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
            'email' => $name.'@example.test',
            'username' => $name,
            'password' => self::PASSWORD,
            'phone_hash' => hash('sha256', $name),
        ], $extra));
    }

    private function login(string $name, array $extra = []): TestResponse
    {
        return $this->postJson('/api/v1/auth/password', array_merge([
            'username' => $name, 'password' => self::PASSWORD, 'locale' => 'en',
        ], $extra));
    }

    public function test_known_gap_unconfigured_staff_can_log_in_with_password_alone(): void
    {
        $this->account('coordinator', 'mfa.unconfigured');

        $this->login('mfa.unconfigured')->assertOk();
    }

    public function test_staff_with_totp_needs_a_valid_code_for_password_login(): void
    {
        $this->account('coordinator', 'mfa.totp', ['totp_secret' => 'GEZDGNBVGY3TQOJQ', 'mfa_recovery_codes' => []]);

        $this->login('mfa.totp')->assertStatus(422);
        $this->login('mfa.totp', ['recovery_code' => 'not-a-real-recovery-code'])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_recovery_code_only_staff_needs_a_recovery_code_and_it_is_consumed(): void
    {
        $user = $this->account('coordinator', 'mfa.recovery', [
            'mfa_recovery_codes' => [Hash::make('recover-once-synthetic')],
        ]);

        $this->login('mfa.recovery')->assertStatus(422);
        $this->login('mfa.recovery', ['recovery_code' => 'wrong-code'])->assertStatus(422);
        $this->login('mfa.recovery', ['recovery_code' => 'recover-once-synthetic'])->assertOk();

        $this->assertSame([], $user->fresh()->mfa_recovery_codes);
    }

    public function test_known_gap_using_the_last_recovery_code_of_a_recovery_only_account_disables_mfa(): void
    {
        $this->account('coordinator', 'mfa.lastcode', [
            'mfa_recovery_codes' => [Hash::make('last-code-synthetic')],
        ]);
        $this->login('mfa.lastcode', ['recovery_code' => 'last-code-synthetic'])->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertOk();

        // isConfigured() is now false (no secret, no codes), so MFA is silently off.
        $this->login('mfa.lastcode')->assertOk();
    }

    public function test_inactive_staff_cannot_log_in(): void
    {
        $this->account('coordinator', 'mfa.inactive', ['is_active' => false]);

        $this->login('mfa.inactive')->assertStatus(422);
        $this->assertGuest();
    }

    public function test_patient_password_login_never_asks_for_staff_mfa(): void
    {
        $this->account('patient', 'mfa.patient', ['totp_secret' => 'GEZDGNBVGY3TQOJQ']);

        $this->login('mfa.patient')->assertOk();
    }

    public function test_challenge_requires_mfa_flag_matches_configured_only_rule(): void
    {
        config()->set('royadarman.intake_enabled', false);
        $this->app->instance(OtpSender::class, new StaffMfaCodeCapture);
        $cases = [
            ['09121112241', 'coordinator', [], false],
            ['09121112242', 'coordinator', ['totp_secret' => 'GEZDGNBVGY3TQOJQ', 'mfa_recovery_codes' => []], true],
            ['09121112243', 'patient', ['totp_secret' => 'GEZDGNBVGY3TQOJQ'], false],
        ];

        foreach ($cases as [$mobile, $role, $extra, $expected]) {
            $hash = hash_hmac('sha256', $mobile, (string) config('royadarman.phone_hash_key'));
            User::factory()->create(array_merge(['role' => $role, 'phone' => $mobile, 'phone_hash' => $hash, 'is_active' => true], $extra));

            $this->postJson('/api/v1/auth/otp/challenge', ['mobile' => $mobile, 'locale' => 'en'])
                ->assertStatus(202)
                ->assertJsonPath('data.requires_mfa', $expected);
        }
    }

    public function test_known_gap_unconfigured_staff_can_complete_otp_login_without_mfa(): void
    {
        config()->set('royadarman.intake_enabled', false);
        $sender = new StaffMfaCodeCapture;
        $this->app->instance(OtpSender::class, $sender);
        $mobile = '09121112244';
        $hash = hash_hmac('sha256', $mobile, (string) config('royadarman.phone_hash_key'));
        $user = User::factory()->create(['role' => 'coordinator', 'phone' => $mobile, 'phone_hash' => $hash, 'is_active' => true]);
        $service = $this->app->make(OtpService::class);
        $challenge = $service->challenge($mobile, 'en', '127.0.0.1');

        $this->assertSame($user->id, $service->verify($challenge->id, $sender->code)->id);
    }
}
