<?php

namespace App\Infrastructure\Identity;

use App\Domain\Identity\Contracts\OtpSender;
use App\Infrastructure\Sms\TsmsClient;
use Illuminate\Support\Facades\Lang;
use RuntimeException;

final class TsmsOtpSender implements OtpSender
{
    public function __construct(private readonly TsmsClient $client) {}

    public function send(string $mobile, string $code, string $locale): void
    {
        $locale = in_array($locale, ['fa', 'ar', 'en'], true) ? $locale : 'fa';
        $key = 'notifications.otp';
        $message = Lang::get($key, ['code' => $code], $locale);
        if ($message === $key) {
            throw new RuntimeException('OTP notification translation is missing.');
        }

        $this->client->send($mobile, $message);
    }
}
