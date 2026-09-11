<?php

namespace App\Infrastructure\Sms;

use App\Support\DigitNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TsmsClient
{
    private const ERRORS = [
        '1' => 'TSMS server error.',
        '2' => 'TSMS rejected the message because the UDH payload is too large.',
        '3' => 'TSMS rejected the destination mobile number.',
        '4' => 'TSMS rejected the request parameters.',
        '5' => 'TSMS rejected an empty message.',
        '6' => 'TSMS rejected an empty destination mobile number.',
        '7' => 'TSMS authentication failed.',
        '8' => 'TSMS reported a temporary server error.',
        '9' => 'TSMS SMS delivery service is unavailable.',
        '14' => 'TSMS account credit is insufficient.',
    ];

    public function send(string $mobile, string $message): string
    {
        $mobile = DigitNormalizer::iranianMobile($mobile);
        if (! preg_match('/^09\d{9}$/', $mobile)) {
            throw new RuntimeException('TSMS destination mobile number is invalid.');
        }

        $message = trim($message);
        if ($message === '') {
            throw new RuntimeException('TSMS message must not be empty.');
        }

        [$endpoint, $username, $password, $from] = $this->configuration();

        try {
            $response = Http::accept('text/plain')
                ->timeout(30)
                ->retry(2, 250)
                ->withOptions(['allow_redirects' => false])
                ->get($endpoint, [
                    'from' => $from,
                    'to' => $mobile,
                    'username' => $username,
                    'password' => $password,
                    'message' => $message,
                ]);
        } catch (ConnectionException) {
            // Do not propagate the underlying exception because it may contain a
            // URL query string with provider credentials.
            throw new RuntimeException('TSMS connection failed.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('TSMS returned HTTP '.$response->status().'.');
        }

        return $this->parseResponse($response->body());
    }

    /** @return array{0:string,1:string,2:string,3:string} */
    private function configuration(): array
    {
        $endpoint = trim((string) config('royadarman.sms.tsms.endpoint'));
        $username = trim((string) config('royadarman.sms.tsms.username'));
        $password = (string) config('royadarman.sms.tsms.password');
        $from = trim((string) config('royadarman.sms.tsms.from'));

        if ($username === '' || $password === '' || $from === '') {
            throw new RuntimeException('TSMS credentials/sender are not configured.');
        }

        $scheme = strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        $path = (string) parse_url($endpoint, PHP_URL_PATH);
        if ($scheme !== 'https'
            || ! in_array($host, ['tsms.ir', 'www.tsms.ir'], true)
            || $path !== '/url/tsmshttp.php') {
            throw new RuntimeException('TSMS API endpoint is not allowed; HTTPS to the approved TSMS URL API endpoint is required.');
        }

        return [$endpoint, $username, $password, $from];
    }

    private function parseResponse(string $raw): string
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $result = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $result = preg_replace('/\s+/u', '', $result) ?? $result;

        if ($result === '') {
            throw new RuntimeException('TSMS returned an empty response.');
        }
        if (isset(self::ERRORS[$result])) {
            throw new RuntimeException(self::ERRORS[$result]);
        }
        if (str_starts_with($result, '-')) {
            throw new RuntimeException('TSMS rejected the request with code '.$result.'.');
        }
        if (! preg_match('/^[0-9A-Za-z,._:\-]{1,190}$/', $result)) {
            throw new RuntimeException('TSMS returned an unrecognised response.');
        }

        return $result;
    }
}
