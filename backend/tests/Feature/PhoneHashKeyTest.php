<?php

namespace Tests\Feature;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Services\OtpService;
use App\Support\PhoneHasher;
use Tests\TestCase;

final class PhoneHashKeyTest extends TestCase
{
    public function test_valid_key_hashes_consistently(): void
    {
        $hasher = $this->app->make(PhoneHasher::class);

        $this->assertSame(
            $hasher->hash('09121234567'),
            $hasher->hash('09121234567')
        );
    }

    public function test_empty_key_fails_fast(): void
    {
        config(['royadarman.phone_hash_key' => '']);
        $this->app->forgetInstance(PhoneHasher::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/phone lookup key/i');

        $this->app->make(PhoneHasher::class);
    }

    public function test_missing_key_fails_fast(): void
    {
        config(['royadarman.phone_hash_key' => null]);
        $this->app->forgetInstance(PhoneHasher::class);

        $this->expectException(\RuntimeException::class);
        $this->app->make(PhoneHasher::class);
    }

    public function test_otp_challenge_refuses_to_start_without_a_key(): void
    {
        config(['royadarman.phone_hash_key' => '']);
        $this->app->forgetInstance(OtpService::class);
        $this->app->forgetInstance(PhoneHasher::class);
        $this->app->forgetInstance(OtpSender::class);

        $sender = new class implements OtpSender
        {
            public string $code = '';

            public function send(string $mobile, string $code, string $locale): void
            {
                $this->code = $code;
            }
        };
        $this->app->instance(OtpSender::class, $sender);

        $this->expectException(\RuntimeException::class);
        $this->app->make(OtpService::class)->challenge('09121234567', 'fa', '127.0.0.1');
    }
}
