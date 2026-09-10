<?php

namespace Tests\Feature;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Operations\Exceptions\SmsDeliveryException;
use App\Domain\Operations\Services\SmsManager;
use App\Infrastructure\Operations\HttpSmsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SmsProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_http_provider_sends_and_returns_reference_with_idempotency_header(): void
    {
        config()->set('royadarman.sms.endpoint', 'https://sms.test/send');
        config()->set('royadarman.sms.token', 'secret-token');

        Http::fake([
            'sms.test/send' => Http::response(['reference' => 'provider-ref-123'], 200),
        ]);

        $provider = $this->app->make(HttpSmsProvider::class);

        $ref = $provider->send('09120000000', 'case_submitted', 'fa', ['reference' => 'RD-1'], 'idem-key-1');

        $this->assertSame('provider-ref-123', $ref);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://sms.test/send'
                && $request->hasHeader('Idempotency-Key', 'idem-key-1')
                && $request->hasHeader('Authorization', 'Bearer secret-token')
                && $request['recipient'] === '09120000000'
                && $request['template'] === 'case_submitted_fa';
        });
    }

    public function test_http_provider_throws_not_configured_without_endpoint_or_token(): void
    {
        config()->set('royadarman.sms.endpoint', null);
        config()->set('royadarman.sms.token', null);

        $this->expectException(SmsDeliveryException::class);
        $this->expectExceptionMessageMatches('/not configured/');

        $this->app->make(HttpSmsProvider::class)->send('09120000000', 'case_submitted', 'fa', [], 'k');
    }

    public function test_http_provider_throws_quota_exceeded_on_429(): void
    {
        config()->set('royadarman.sms.endpoint', 'https://sms.test/send');
        config()->set('royadarman.sms.token', 'token');

        Http::fake(['sms.test/send' => Http::response(['error' => 'rate limited'], 429)]);

        $this->expectException(SmsDeliveryException::class);
        $this->expectExceptionMessageMatches('/quota exceeded/');

        $this->app->make(HttpSmsProvider::class)->send('09120000000', 'case_submitted', 'fa', [], 'k');
    }

    public function test_http_provider_throws_on_failed_response(): void
    {
        config()->set('royadarman.sms.endpoint', 'https://sms.test/send');
        config()->set('royadarman.sms.token', 'token');

        Http::fake(['sms.test/send' => Http::response(['error' => 'bad'], 500)]);

        $this->expectException(SmsDeliveryException::class);
        $this->expectExceptionMessageMatches('/HTTP 500/');

        $this->app->make(HttpSmsProvider::class)->send('09120000000', 'case_submitted', 'fa', [], 'k');
    }

    public function test_callback_signature_verification_accepts_valid_signature(): void
    {
        config()->set('royadarman.sms.callback_secret', 'test-secret');
        $body = json_encode(['reference' => 'ref-1', 'status' => 'delivered']);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'test-secret');

        $provider = $this->app->make(HttpSmsProvider::class);
        $headers = [
            'x-callback-timestamp' => $timestamp,
            'x-callback-signature' => $signature,
        ];

        $this->assertTrue($provider->verifyCallback($body, $headers));
    }

    public function test_callback_signature_verification_rejects_invalid_signature(): void
    {
        config()->set('royadarman.sms.callback_secret', 'test-secret');
        $body = json_encode(['reference' => 'ref-1', 'status' => 'delivered']);
        $timestamp = (string) time();

        $provider = $this->app->make(HttpSmsProvider::class);
        $headers = [
            'x-callback-timestamp' => $timestamp,
            'x-callback-signature' => 'wrong-signature',
        ];

        $this->assertFalse($provider->verifyCallback($body, $headers));
    }

    public function test_callback_rejects_stale_timestamp_outside_5_minute_window(): void
    {
        config()->set('royadarman.sms.callback_secret', 'test-secret');
        $body = json_encode(['reference' => 'ref-1', 'status' => 'delivered']);
        $staleTimestamp = (string) (time() - 600);
        $signature = hash_hmac('sha256', $staleTimestamp.'.'.$body, 'test-secret');

        $provider = $this->app->make(HttpSmsProvider::class);
        $headers = [
            'x-callback-timestamp' => $staleTimestamp,
            'x-callback-signature' => $signature,
        ];

        $this->assertFalse($provider->verifyCallback($body, $headers));
    }

    public function test_callback_rejects_missing_secret(): void
    {
        config()->set('royadarman.sms.callback_secret', null);
        $provider = $this->app->make(HttpSmsProvider::class);

        $this->assertFalse($provider->verifyCallback('body', ['x-callback-timestamp' => (string) time(), 'x-callback-signature' => 'x']));
    }

    public function test_parse_callback_extracts_reference_status_and_failure_code(): void
    {
        $body = json_encode(['reference' => 'ref-1', 'status' => 'failed', 'failure_code' => 'INSUFFICIENT_CREDIT']);
        $provider = $this->app->make(HttpSmsProvider::class);

        $parsed = $provider->parseCallback($body, []);

        $this->assertSame('ref-1', $parsed['reference']);
        $this->assertSame('failed', $parsed['status']);
        $this->assertSame('INSUFFICIENT_CREDIT', $parsed['failure_code']);
    }

    public function test_parse_callback_handles_missing_failure_code_as_null(): void
    {
        $body = json_encode(['reference' => 'ref-1', 'status' => 'delivered']);
        $provider = $this->app->make(HttpSmsProvider::class);

        $parsed = $provider->parseCallback($body, []);

        $this->assertSame('delivered', $parsed['status']);
        $this->assertNull($parsed['failure_code']);
    }

    public function test_sms_manager_resolves_registered_provider(): void
    {
        $manager = $this->app->make(SmsManager::class);
        $provider = $manager->provider('http');

        $this->assertInstanceOf(HttpSmsProvider::class, $provider);
    }

    public function test_sms_manager_throws_for_unregistered_provider(): void
    {
        $manager = $this->app->make(SmsManager::class);

        $this->expectException(\InvalidArgumentException::class);
        $manager->provider('nonexistent');
    }

    public function test_otp_sender_delegates_to_sms_provider(): void
    {
        config()->set('royadarman.sms.endpoint', 'https://sms.test/send');
        config()->set('royadarman.sms.token', 'token');

        Http::fake(['sms.test/send' => Http::response(['reference' => 'otp-ref'], 200)]);

        $otpSender = $this->app->make(OtpSender::class);
        $otpSender->send('09120000000', '123456', 'fa');

        Http::assertSent(function ($request): bool {
            return $request['recipient'] === '09120000000'
                && $request['template'] === 'royadarman_otp_fa'
                && $request['parameters'] === ['code' => '123456'];
        });
    }
}
