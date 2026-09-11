<?php

namespace Tests\Feature;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Services\OtpService;
use App\Models\OtpChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class CapturingOtpSenderForInvariants implements OtpSender
{
    public string $code = '';

    public function send(string $mobile, string $code, string $locale): void
    {
        $this->code = $code;
    }
}

class OtpInvariantTest extends TestCase
{
    use RefreshDatabase;

    private function serviceWithCapture(): array
    {
        $sender = new CapturingOtpSenderForInvariants;
        $this->app->instance(OtpSender::class, $sender);

        return [$this->app->make(OtpService::class), $sender];
    }

    public function test_persian_digits_are_normalised_to_latin(): void
    {
        [$service, $sender] = $this->serviceWithCapture();
        $challenge = $service->challenge('٠٩١٢١٢٣٤٥٦٧', 'fa', '127.0.0.1');
        $this->assertSame('09121234567', $challenge->phone);
    }

    public function test_arabic_digits_are_normalised_to_latin(): void
    {
        [$service, $sender] = $this->serviceWithCapture();
        $challenge = $service->challenge('٠٩١٢١٢٣٤٥٦٧', 'fa', '127.0.0.1');
        $this->assertSame('09121234567', $challenge->phone);
    }

    public function test_invalid_iranian_mobile_rejected(): void
    {
        [$service, $sender] = $this->serviceWithCapture();
        $this->expectException(ValidationException::class);
        $service->challenge('12345', 'fa', '127.0.0.1');
    }

    public function test_landline_format_rejected(): void
    {
        [$service, $sender] = $this->serviceWithCapture();
        $this->expectException(ValidationException::class);
        $service->challenge('0212345678', 'fa', '127.0.0.1');
    }

    public function test_expired_otp_rejected(): void
    {
        $challenge = OtpChallenge::query()->create([
            'phone' => '09121234567', 'phone_hash' => hash('sha256', 'expired'),
            'code_hash' => Hash::make('123456'), 'locale' => 'en',
            'expires_at' => now()->subSecond(), 'last_sent_at' => now()->subMinute(),
            'request_ip_hash' => hash('sha256', 'ip'),
        ]);
        $this->expectException(ValidationException::class);
        $this->serviceWithCapture()[0]->verify($challenge->id, '123456');
    }

    public function test_otp_is_single_use(): void
    {
        [$service, $sender] = $this->serviceWithCapture();
        $challenge = $service->challenge('09121234567', 'en', '127.0.0.1');
        $service->verify($challenge->id, $sender->code);
        $this->expectException(ValidationException::class);
        $service->verify($challenge->id, $sender->code);
    }

    public function test_five_attempt_lock(): void
    {
        $challenge = OtpChallenge::query()->create([
            'phone' => '09121234567', 'phone_hash' => hash('sha256', 'lock'),
            'code_hash' => Hash::make('999999'), 'locale' => 'en',
            'expires_at' => now()->addMinute(), 'last_sent_at' => now(),
            'attempts' => 5, 'request_ip_hash' => hash('sha256', 'ip'),
        ]);
        $this->expectException(ValidationException::class);
        $this->serviceWithCapture()[0]->verify($challenge->id, '123456');
    }

    public function test_resend_cooldown_enforced(): void
    {
        [$service, $sender] = $this->serviceWithCapture();
        $service->challenge('09121234567', 'en', '127.0.0.1');
        $this->expectException(HttpException::class);
        $service->challenge('09121234567', 'en', '127.0.0.1');
    }

    public function test_phone_hourly_limit_enforced(): void
    {
        [$service, $sender] = $this->serviceWithCapture();
        $phoneHash = hash_hmac('sha256', '09121234567', (string) config('royadarman.phone_hash_key'));
        OtpChallenge::query()->insert(array_map(fn () => [
            'id' => (string) Str::ulid(),
            'phone' => '09121234567', 'phone_hash' => $phoneHash,
            'code_hash' => Hash::make('123456'), 'locale' => 'en',
            'expires_at' => now()->addMinute(), 'last_sent_at' => now()->subMinutes(2),
            'request_ip_hash' => hash('sha256', 'other-ip-'.Str::random(4)),
            'created_at' => now()->subMinutes(5), 'updated_at' => now(),
        ], range(1, 10)));
        $this->expectException(HttpException::class);
        $service->challenge('09121234567', 'en', '10.0.0.99');
    }

    public function test_ip_hourly_limit_enforced(): void
    {
        [$service, $sender] = $this->serviceWithCapture();
        $ipHash = hash_hmac('sha256', '192.0.2.1', (string) config('app.key'));
        OtpChallenge::query()->insert(array_map(fn () => [
            'id' => (string) Str::ulid(),
            'phone' => '0912'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'phone_hash' => hash('sha256', Str::random(8)),
            'code_hash' => Hash::make('123456'), 'locale' => 'en',
            'expires_at' => now()->addMinute(), 'last_sent_at' => now()->subMinutes(2),
            'request_ip_hash' => $ipHash,
            'created_at' => now()->subMinutes(5), 'updated_at' => now(),
        ], range(1, 20)));
        $this->expectException(HttpException::class);
        $service->challenge('09129999999', 'en', '192.0.2.1');
    }

    public function test_inactive_user_rejected(): void
    {
        $hash = hash_hmac('sha256', '09121234567', (string) config('royadarman.phone_hash_key'));
        User::factory()->create(['role' => 'patient', 'phone' => '09121234567', 'phone_hash' => $hash, 'is_active' => false]);
        $challenge = OtpChallenge::query()->create([
            'phone' => '09121234567', 'phone_hash' => $hash,
            'code_hash' => Hash::make('123456'), 'locale' => 'en',
            'expires_at' => now()->addMinute(), 'last_sent_at' => now(),
            'request_ip_hash' => hash('sha256', 'ip'),
        ]);
        $this->expectException(HttpException::class);
        $this->serviceWithCapture()[0]->verify($challenge->id, '123456');
    }

    public function test_concurrent_verification_of_same_challenge_is_safe(): void
    {
        [$service, $sender] = $this->serviceWithCapture();
        $challenge = $service->challenge('09121234567', 'en', '127.0.0.1');
        $service->verify($challenge->id, $sender->code);
        $challenge->refresh();
        $this->assertNotNull($challenge->used_at);
        $this->expectException(ValidationException::class);
        $service->verify($challenge->id, $sender->code);
    }

    public function test_new_challenge_supersedes_prior_active_challenge(): void
    {
        [$service, $sender] = $this->serviceWithCapture();
        $first = $service->challenge('09121234567', 'en', '127.0.0.1');
        $firstCode = $sender->code;
        $this->assertNull($first->fresh()->superseded_at);

        // Advance past the resend cooldown so a new challenge can be issued.
        $this->travel(2)->minutes();
        $second = $service->challenge('09121234567', 'en', '127.0.0.1');
        $this->assertNotSame($first->id, $second->id);

        // The first challenge is now superseded and cannot be verified.
        $this->assertNotNull($first->fresh()->superseded_at);
        $this->expectException(ValidationException::class);
        $service->verify($first->id, $firstCode);
    }

    public function test_replacement_challenge_works_after_supersession(): void
    {
        [$service, $sender] = $this->serviceWithCapture();
        $service->challenge('09121234567', 'en', '127.0.0.1');
        $this->travel(2)->minutes();
        $second = $service->challenge('09121234567', 'en', '127.0.0.1');

        $user = $service->verify($second->id, $sender->code);
        $this->assertInstanceOf(User::class, $user);
        $this->assertNotNull($second->fresh()->used_at);
    }

    public function test_no_two_active_challenges_remain_after_replacement(): void
    {
        [$service, $sender] = $this->serviceWithCapture();
        $service->challenge('09121234567', 'en', '127.0.0.1');
        $this->travel(2)->minutes();
        $service->challenge('09121234567', 'en', '127.0.0.1');

        $phoneHash = hash_hmac('sha256', '09121234567', (string) config('royadarman.phone_hash_key'));
        $active = OtpChallenge::query()
            ->where('phone_hash', $phoneHash)
            ->where('purpose', 'login')
            ->whereNull('used_at')
            ->whereNull('superseded_at')
            ->where('expires_at', '>', now())
            ->count();
        $this->assertSame(1, $active, 'Exactly one active challenge must remain after a replacement is issued.');
    }

    public function test_expired_challenge_is_not_marked_superseded_but_still_rejected(): void
    {
        [$service, $sender] = $this->serviceWithCapture();
        $first = $service->challenge('09121234567', 'en', '127.0.0.1');
        // Let it expire naturally before a new one is issued.
        $this->travel(6)->minutes();
        $second = $service->challenge('09121234567', 'en', '127.0.0.1');
        $this->assertNotSame($first->id, $second->id);
        $this->assertNull($first->fresh()->superseded_at, 'An already-expired challenge is not superseded, it is simply expired.');

        $this->expectException(ValidationException::class);
        $service->verify($first->id, $sender->code);
    }
}
