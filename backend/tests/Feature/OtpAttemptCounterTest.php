<?php

namespace Tests\Feature;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Services\OtpService;
use App\Models\OtpChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class OtpAttemptCodeCapture implements OtpSender
{
    public string $code = '';

    public function send(string $mobile, string $code, string $locale): void
    {
        $this->code = $code;
    }
}

/**
 * Synthetic data only. NOT RUN when authored: requires independent execution.
 * Expected to FAIL on main f99210e (attempts rolled back) and pass with the fix.
 */
class OtpAttemptCounterTest extends TestCase
{
    use RefreshDatabase;

    private OtpAttemptCodeCapture $sender;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('royadarman.intake_enabled', false);
        config()->set('royadarman.sms.otp.max_attempts', 5);
        $this->sender = new OtpAttemptCodeCapture;
        $this->app->instance(OtpSender::class, $this->sender);
    }

    private function challengeFor(string $mobile, array $user): OtpChallenge
    {
        $hash = hash_hmac('sha256', $mobile, (string) config('royadarman.phone_hash_key'));
        User::factory()->create(array_merge(['phone' => $mobile, 'phone_hash' => $hash, 'is_active' => true], $user));

        return $this->app->make(OtpService::class)->challenge($mobile, 'en', '127.0.0.1');
    }

    private function wrongCode(): string
    {
        return $this->sender->code === '000000' ? '111111' : '000000';
    }

    private function assertInvalid(callable $attempt, string $field): void
    {
        try {
            $attempt();
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_wrong_codes_are_counted_and_lock_the_challenge(): void
    {
        $challenge = $this->challengeFor('09121112231', ['role' => 'patient']);
        $service = $this->app->make(OtpService::class);

        for ($i = 0; $i < 5; $i++) {
            $this->assertInvalid(fn () => $service->verify($challenge->id, $this->wrongCode()), 'code');
        }

        $this->assertSame(5, $challenge->fresh()->attempts);

        $this->assertInvalid(fn () => $service->verify($challenge->id, $this->sender->code), 'code');
        $this->assertNull($challenge->fresh()->used_at);
    }

    public function test_failed_mfa_after_correct_otp_is_counted_and_challenge_stays_unused(): void
    {
        $challenge = $this->challengeFor('09121112232', [
            'role' => 'coordinator', 'totp_secret' => 'GEZDGNBVGY3TQOJQ', 'mfa_recovery_codes' => [],
        ]);

        $this->assertInvalid(
            fn () => $this->app->make(OtpService::class)->verify($challenge->id, $this->sender->code),
            'totp_code',
        );

        $fresh = $challenge->fresh();
        $this->assertSame(1, $fresh->attempts);
        $this->assertNull($fresh->used_at);
    }

    public function test_correct_code_after_a_few_wrong_ones_still_succeeds_and_is_single_use(): void
    {
        $challenge = $this->challengeFor('09121112233', ['role' => 'patient']);
        $service = $this->app->make(OtpService::class);

        for ($i = 0; $i < 2; $i++) {
            $this->assertInvalid(fn () => $service->verify($challenge->id, $this->wrongCode()), 'code');
        }

        $user = $service->verify($challenge->id, $this->sender->code);

        $this->assertSame('patient', $user->role->value);
        $this->assertNotNull($challenge->fresh()->used_at);
        $this->assertSame(3, $challenge->fresh()->attempts);
        $this->assertInvalid(fn () => $service->verify($challenge->id, $this->sender->code), 'code');
    }

    public function test_used_challenge_does_not_increment_attempts(): void
    {
        $challenge = $this->challengeFor('09121112234', ['role' => 'patient']);
        $service = $this->app->make(OtpService::class);
        $service->verify($challenge->id, $this->sender->code);
        $before = $challenge->fresh()->attempts;

        $this->assertInvalid(fn () => $service->verify($challenge->id, $this->sender->code), 'code');
        $this->assertSame($before, $challenge->fresh()->attempts);
    }
}
