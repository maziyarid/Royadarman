<?php

namespace Tests\Feature;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Services\OtpService;
use App\Domain\Identity\Services\TotpVerifier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StaffMfaLoginPathsCodeCapture implements OtpSender
{
    public string $code = '';

    public function send(string $mobile, string $code, string $locale): void
    {
        $this->code = $code;
    }
}

/**
 * Closes two coverage gaps raised in review of PR #19: a VALID TOTP must log in,
 * and the OTP HTTP endpoint (not only the service) must establish the session.
 * CHARACTERIZATION of main f99210e; not a policy endorsement. Synthetic data.
 * NOT RUN when authored.
 */
class StaffMfaLoginPathsCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'GEZDGNBVGY3TQOJQ';

    private function currentTotp(): string
    {
        $verifier = $this->app->make(TotpVerifier::class);
        $decode = (new \ReflectionClass($verifier))->getMethod('decodeBase32');
        $decode->setAccessible(true);
        $secret = $decode->invoke($verifier, self::SECRET);
        $hash = hash_hmac('sha1', pack('N*', 0).pack('N*', intdiv(time(), 30)), $secret, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8) | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    public function test_staff_with_a_valid_totp_can_log_in_with_password(): void
    {
        User::factory()->create([
            'role' => 'coordinator', 'is_active' => true, 'email' => 'mfa.valid@example.test',
            'username' => 'mfa.valid', 'password' => 'synthetic-Password-123',
            'phone_hash' => hash('sha256', 'mfa.valid'),
            'totp_secret' => self::SECRET, 'mfa_recovery_codes' => [],
        ]);

        $this->postJson('/api/v1/auth/password', [
            'username' => 'mfa.valid', 'password' => 'synthetic-Password-123',
            'totp_code' => $this->currentTotp(), 'locale' => 'en',
        ])->assertOk();

        $this->assertAuthenticated();
    }

    public function test_known_gap_otp_endpoint_logs_in_unconfigured_staff_without_mfa(): void
    {
        config()->set('royadarman.intake_enabled', false);
        $sender = new StaffMfaLoginPathsCodeCapture;
        $this->app->instance(OtpSender::class, $sender);
        $mobile = '09121112245';
        $hash = hash_hmac('sha256', $mobile, (string) config('royadarman.phone_hash_key'));
        $user = User::factory()->create(['role' => 'coordinator', 'phone' => $mobile, 'phone_hash' => $hash, 'is_active' => true]);
        $challenge = $this->app->make(OtpService::class)->challenge($mobile, 'en', '127.0.0.1');

        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challenge->id, 'code' => $sender->code])
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }
}
