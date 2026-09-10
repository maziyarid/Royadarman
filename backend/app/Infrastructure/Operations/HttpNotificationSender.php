<?php

namespace App\Infrastructure\Operations;

use App\Domain\Operations\Contracts\NotificationSender;
use App\Domain\Operations\Services\SmsManager;

final class HttpNotificationSender implements NotificationSender
{
    public function __construct(
        private readonly SmsManager $sms,
    ) {}

    public function send(string $mobile, string $template, string $locale, array $parameters, string $idempotencyKey): string
    {
        return $this->sms->provider()->send($mobile, $template, $locale, $parameters, $idempotencyKey);
    }
}
