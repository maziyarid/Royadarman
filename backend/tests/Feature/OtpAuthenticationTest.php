<?php

namespace Tests\Feature;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Services\OtpService;
use App\Models\OtpChallenge;
use App\Models\User;
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
}
