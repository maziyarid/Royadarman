<?php

namespace App\Infrastructure\Identity;

use App\Domain\Identity\Contracts\OtpSender;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HttpOtpSender implements OtpSender
{
    public function send(string $mobile, string $code, string $locale): void
    {
        $endpoint = config('royadarman.sms.endpoint');
        $token = config('royadarman.sms.token');

        if (! is_string($endpoint) || $endpoint === '' || ! is_string($token) || $token === '') {
            throw new RuntimeException('OTP delivery is not configured.');
        }

        Http::asJson()->withToken($token)->timeout(8)->retry(2, 250)->post($endpoint, [
            'recipient' => $mobile,
            'template' => 'royadarman_otp_'.$locale,
            'parameters' => ['code' => $code],
        ])->throw();
    }
}

