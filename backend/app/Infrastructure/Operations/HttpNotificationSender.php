<?php

namespace App\Infrastructure\Operations;

use App\Domain\Operations\Contracts\NotificationSender;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HttpNotificationSender implements NotificationSender
{
    public function send(string $mobile, string $template, string $locale, array $parameters, string $idempotencyKey): string
    {
        $endpoint = config('royadarman.sms.endpoint');
        $token = config('royadarman.sms.token');
        if (! is_string($endpoint) || $endpoint === '' || ! is_string($token) || $token === '') {
            throw new RuntimeException('Notification delivery is not configured.');
        }
        if (strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https'
            || trim((string) parse_url($endpoint, PHP_URL_HOST)) === '') {
            throw new RuntimeException('Notification delivery endpoint must use HTTPS.');
        }

        $response = Http::asJson()
            ->withToken($token)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->timeout(8)
            ->retry(2, 250)
            ->withOptions(['allow_redirects' => false])
            ->post($endpoint, [
                'recipient' => $mobile,
                'template' => $template.'_'.$locale,
                'parameters' => $parameters,
            ])->throw()->json();

        return (string) ($response['reference'] ?? $idempotencyKey);
    }
}
