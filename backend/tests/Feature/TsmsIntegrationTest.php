<?php

namespace Tests\Feature;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Operations\Contracts\NotificationSender;
use App\Infrastructure\Identity\TsmsOtpSender;
use App\Infrastructure\Operations\TsmsNotificationSender;
use App\Infrastructure\Sms\TsmsClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class TsmsIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('royadarman.sms.provider', 'tsms');
        config()->set('royadarman.sms.tsms.endpoint', 'https://tsms.ir/url/tsmshttp.php');
        config()->set('royadarman.sms.tsms.username', 'test-user');
        config()->set('royadarman.sms.tsms.password', 'test-password');
        config()->set('royadarman.sms.tsms.from', '30001234');
    }

    public function test_tsms_client_sends_expected_url_api_request_and_returns_sms_id(): void
    {
        Http::fake(['tsms.ir/*' => Http::response('sms-12345', 200)]);

        $reference = $this->app->make(TsmsClient::class)->send('09121234567', 'test message');

        $this->assertSame('sms-12345', $reference);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://tsms.ir/url/tsmshttp.php?')
            && $request['from'] === '30001234'
            && $request['to'] === '09121234567'
            && $request['username'] === 'test-user'
            && $request['password'] === 'test-password'
            && $request['message'] === 'test message');
    }

    public function test_tsms_error_code_is_not_treated_as_success(): void
    {
        Http::fake(['tsms.ir/*' => Http::response('14', 200)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('credit');
        $this->app->make(TsmsClient::class)->send('09121234567', 'test message');
    }

    public function test_unapproved_tsms_endpoint_fails_before_network_request(): void
    {
        config()->set('royadarman.sms.tsms.endpoint', 'https://example.com/url/tsmshttp.php');
        Http::fake();

        try {
            $this->app->make(TsmsClient::class)->send('09121234567', 'test message');
            $this->fail('Expected invalid endpoint to fail closed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('endpoint', strtolower($exception->getMessage()));
        }

        Http::assertNothingSent();
    }

    public function test_container_binds_otp_sender_to_tsms_and_localises_message(): void
    {
        Http::fake(['tsms.ir/*' => Http::response('otp-1', 200)]);

        $sender = $this->app->make(OtpSender::class);
        $this->assertInstanceOf(TsmsOtpSender::class, $sender);
        $sender->send('09121234567', '482951', 'en');

        Http::assertSent(fn ($request) => str_contains((string) $request['message'], '482951')
            && str_contains((string) $request['message'], 'Royadarman'));
    }

    public function test_notification_sender_renders_known_template_and_returns_provider_reference(): void
    {
        Http::fake(['tsms.ir/*' => Http::response('notice-7', 200)]);

        $sender = $this->app->make(NotificationSender::class);
        $this->assertInstanceOf(TsmsNotificationSender::class, $sender);
        $reference = $sender->send('09121234567', 'case_submitted', 'en', ['reference' => 'RD-ABC123'], 'idempotency-1');

        $this->assertSame('notice-7', $reference);
        Http::assertSent(fn ($request) => str_contains((string) $request['message'], 'RD-ABC123'));
    }

    public function test_notification_sender_rejects_unknown_template(): void
    {
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->app->make(NotificationSender::class)
            ->send('09121234567', 'not_a_real_template', 'fa', [], 'idempotency-2');
    }
}
