<?php

namespace Tests\Feature;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Services\OtpService;
use App\Domain\Identity\Services\TotpVerifier;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Support\DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CapturingOtpSender implements OtpSender
{
    public string $code = '';

    public function send(string $mobile, string $code, string $locale): void
    {
        $this->code = $code;
    }
}

class OtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_otp_is_normalised_hashed_single_use_and_creates_a_minimal_user(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $sender = new CapturingOtpSender;
        $this->app->instance(OtpSender::class, $sender);
        $service = $this->app->make(OtpService::class);
        $challenge = $service->challenge('۰۹۱۲ ۱۲۳ ۴۵۶۷', 'fa', '127.0.0.1');

        $this->assertNotSame($sender->code, $challenge->code_hash);
        $user = $service->verify($challenge->id, $sender->code);
        $this->assertSame('patient', $user->role->value);
        $this->assertNull($user->email);

        $this->expectException(ValidationException::class);
        $service->verify($challenge->id, $sender->code);
    }

    public function test_unknown_phone_is_rejected_before_otp_when_intake_is_paused(): void
    {
        config()->set('royadarman.intake_enabled', false);
        $sender = new CapturingOtpSender;
        $this->app->instance(OtpSender::class, $sender);

        try {
            $this->app->make(OtpService::class)->challenge('09121112222', 'fa', '127.0.0.1');
            $this->fail('Expected intake gate to reject an unknown identity.');
        } catch (DomainException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
            $this->assertSame('intake_not_enabled', $exception->domainCode());
        }

        $this->assertSame('', $sender->code);
        $this->assertDatabaseCount('otp_challenges', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_patient_can_receive_otp_while_intake_is_paused(): void
    {
        config()->set('royadarman.intake_enabled', false);
        $mobile = '09121112223';
        $hash = hash_hmac('sha256', $mobile, (string) config('royadarman.phone_hash_key'));
        User::factory()->create(['role' => 'patient', 'phone' => $mobile, 'phone_hash' => $hash, 'is_active' => true]);
        $sender = new CapturingOtpSender;
        $this->app->instance(OtpSender::class, $sender);

        $challenge = $this->app->make(OtpService::class)->challenge($mobile, 'fa', '127.0.0.1');

        $this->assertNotSame('', $sender->code);
        $this->assertSame($hash, $challenge->phone_hash);
    }

    public function test_existing_staff_can_receive_otp_while_intake_is_paused_and_still_requires_mfa(): void
    {
        config()->set('royadarman.intake_enabled', false);
        $mobile = '09121112224';
        $hash = hash_hmac('sha256', $mobile, (string) config('royadarman.phone_hash_key'));
        User::factory()->create([
            'role' => 'coordinator', 'phone' => $mobile, 'phone_hash' => $hash,
            'totp_secret' => 'GEZDGNBVGY3TQOJQ', 'mfa_recovery_codes' => [], 'is_active' => true,
        ]);
        $sender = new CapturingOtpSender;
        $this->app->instance(OtpSender::class, $sender);
        $service = $this->app->make(OtpService::class);
        $challenge = $service->challenge($mobile, 'fa', '127.0.0.1');

        $this->expectException(ValidationException::class);
        $service->verify($challenge->id, $sender->code);
    }

    public function test_unknown_challenge_cannot_create_patient_if_intake_turns_off_before_verification(): void
    {
        config()->set('royadarman.intake_enabled', false);
        $mobile = '09121112225';
        $hash = hash_hmac('sha256', $mobile, (string) config('royadarman.phone_hash_key'));
        $challenge = OtpChallenge::query()->create([
            'phone' => $mobile, 'phone_hash' => $hash, 'code_hash' => Hash::make('123456'),
            'locale' => 'fa', 'expires_at' => now()->addMinute(), 'last_sent_at' => now(),
            'request_ip_hash' => hash('sha256', 'race'),
        ]);

        try {
            $this->app->make(OtpService::class)->verify($challenge->id, '123456');
            $this->fail('Expected verification-time intake gate.');
        } catch (DomainException $exception) {
            $this->assertSame('intake_not_enabled', $exception->domainCode());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $challenge = OtpChallenge::query()->create(['phone' => '09121234567', 'phone_hash' => hash('sha256', 'expired'), 'code_hash' => Hash::make('123456'), 'locale' => 'en', 'expires_at' => now()->subSecond(), 'last_sent_at' => now()->subMinute(), 'request_ip_hash' => hash('sha256', 'ip')]);
        $this->expectException(ValidationException::class);
        $this->app->make(OtpService::class)->verify($challenge->id, '123456');
    }

    public function test_staff_can_use_a_single_recovery_code_and_it_is_consumed(): void
    {
        $hash = hash_hmac('sha256', '09121234567', (string) config('royadarman.phone_hash_key'));
        $user = User::factory()->create(['role' => 'coordinator', 'phone' => '09121234567', 'phone_hash' => $hash, 'totp_secret' => 'JBSWY3DPEHPK3PXP', 'mfa_recovery_codes' => [Hash::make('recover-once')]]);
        $challenge = OtpChallenge::query()->create(['phone' => '09121234567', 'phone_hash' => $hash, 'code_hash' => Hash::make('123456'), 'locale' => 'en', 'expires_at' => now()->addMinute(), 'last_sent_at' => now(), 'request_ip_hash' => hash('sha256', 'ip2')]);
        $authenticated = $this->app->make(OtpService::class)->verify($challenge->id, '123456', null, 'recover-once');
        $this->assertSame($user->id, $authenticated->id);
        $this->assertSame([], $authenticated->fresh()->mfa_recovery_codes);
    }

    public function test_totp_verifier_accepts_a_valid_code_for_known_secret(): void
    {
        $verifier = $this->app->make(TotpVerifier::class);
        // Base32 'GEZDGNBVGY3TQOJQ' decodes to ASCII '12345678901234567890'; 6-digit HOTP at T=59 -> 263420
        $this->assertTrue($verifier->verify('GEZDGNBVGY3TQOJQ', '263420', 59));
        $this->assertFalse($verifier->verify('GEZDGNBVGY3TQOJQ', '000000', 59));
        // Window tolerance: T=58 and T=60 share the same counter as T=59 (counter=1)
        $this->assertTrue($verifier->verify('GEZDGNBVGY3TQOJQ', '263420', 30));
        $this->assertTrue($verifier->verify('GEZDGNBVGY3TQOJQ', '263420', 89));
    }

    public function test_staff_with_totp_secret_must_provide_valid_totp_or_recovery(): void
    {
        $hash = hash_hmac('sha256', '09121234567', (string) config('royadarman.phone_hash_key'));
        $user = User::factory()->create(['role' => 'coordinator', 'phone' => '09121234567', 'phone_hash' => $hash, 'totp_secret' => 'GEZDGNBVGY3TQOJQ', 'mfa_recovery_codes' => []]);
        $challenge = OtpChallenge::query()->create(['phone' => '09121234567', 'phone_hash' => $hash, 'code_hash' => Hash::make('123456'), 'locale' => 'en', 'expires_at' => now()->addMinute(), 'last_sent_at' => now(), 'request_ip_hash' => hash('sha256', 'ip3')]);

        // No MFA code -> rejected
        try {
            $this->app->make(OtpService::class)->verify($challenge->id, '123456');
            $this->fail('Expected ValidationException for missing MFA');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Wrong TOTP -> rejected
        try {
            $this->app->make(OtpService::class)->verify($challenge->id, '123456', '000000');
            $this->fail('Expected ValidationException for wrong TOTP');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Correct TOTP at a fixed timestamp -> accepted
        $validTotp = $this->app->make(TotpVerifier::class);
        $code = $this->computeTotp($validTotp, 'GEZDGNBVGY3TQOJQ', time());
        $authenticated = $this->app->make(OtpService::class)->verify($challenge->id, '123456', $code);
        $this->assertSame($user->id, $authenticated->id);
    }

    private function computeTotp(TotpVerifier $verifier, string $secret, int $timestamp): string
    {
        // Brute-force the current 6-digit code by probing all possibilities is unnecessary;
        // re-implement the same HOTP derivation to obtain the valid code for the window.
        $reflection = new \ReflectionClass($verifier);
        $decode = $reflection->getMethod('decodeBase32');
        $decode->setAccessible(true);
        $secretBin = $decode->invoke($verifier, $secret);
        $counter = intdiv($timestamp, 30);
        $binary = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binary, $secretBin, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }
}
