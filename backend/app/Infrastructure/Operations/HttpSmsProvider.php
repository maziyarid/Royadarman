<?php

namespace App\Infrastructure\Operations;

use App\Domain\Operations\Contracts\SmsProvider;
use App\Domain\Operations\Exceptions\SmsDeliveryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class HttpSmsProvider implements SmsProvider
{
    public function send(string $mobile, string $template, string $locale, array $parameters, string $idempotencyKey): string
    {
        $endpoint = (string) config('royadarman.sms.endpoint');
        $token = (string) config('royadarman.sms.token');

        if ($endpoint === '' || $token === '') {
            throw SmsDeliveryException::notConfigured();
        }

        try {
            $response = Http::asJson()
                ->withToken($token)
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->timeout(8)
                ->retry(2, 250, fn ($exception) => $exception instanceof ConnectionException, throw: false)
                ->post($endpoint, [
                    'recipient' => $mobile,
                    'template' => $template.'_'.$locale,
                    'parameters' => $parameters,
                ]);
        } catch (ConnectionException $e) {
            throw SmsDeliveryException::providerFailed('http', $e->getMessage());
        }

        if ($response->status() === 429) {
            throw SmsDeliveryException::quotaExceeded('http');
        }

        if ($response->failed()) {
            throw SmsDeliveryException::providerFailed('http', 'HTTP '.$response->status());
        }

        $data = $response->json();

        return (string) ($data['reference'] ?? $idempotencyKey);
    }

    public function verifyCallback(string $rawBody, array $headers): bool
    {
        $secret = (string) config('royadarman.sms.callback_secret');
        $timestamp = (string) ($headers['x-callback-timestamp'] ?? '');

        if ($secret === '' || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $signature = (string) ($headers['x-callback-signature'] ?? '');
        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    public function parseCallback(string $rawBody, array $headers): array
    {
        $data = json_decode($rawBody, true) ?? [];

        return [
            'reference' => (string) ($data['reference'] ?? ''),
            'status' => (string) ($data['status'] ?? 'unknown'),
            'failure_code' => isset($data['failure_code']) ? (string) $data['failure_code'] : null,
        ];
    }
}
