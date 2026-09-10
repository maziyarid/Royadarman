<?php

namespace App\Infrastructure\Identity;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Operations\Services\SmsManager;

final class HttpOtpSender implements OtpSender
{
    public function __construct(
        private readonly SmsManager $sms,
    ) {}

    public function send(string $mobile, string $code, string $locale): void
    {
        $this->sms->provider()->send(
            $mobile,
            'royadarman_otp',
            $locale,
            ['code' => $code],
            'otp-'.hash('sha256', $mobile.$code.$locale),
        );
    }
}
