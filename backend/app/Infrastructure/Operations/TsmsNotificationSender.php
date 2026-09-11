<?php

namespace App\Infrastructure\Operations;

use App\Domain\Operations\Contracts\NotificationSender;
use App\Infrastructure\Sms\TsmsClient;
use Illuminate\Support\Facades\Lang;
use RuntimeException;

final class TsmsNotificationSender implements NotificationSender
{
    public function __construct(private readonly TsmsClient $client) {}

    public function send(string $mobile, string $template, string $locale, array $parameters, string $idempotencyKey): string
    {
        $locale = in_array($locale, ['fa', 'ar', 'en'], true) ? $locale : 'fa';
        $key = 'notifications.'.$template;
        $replace = [];
        foreach ($parameters as $name => $value) {
            if (is_scalar($value) || $value === null) {
                $replace[(string) $name] = $value === null ? '' : (string) $value;
            }
        }

        $message = Lang::get($key, $replace, $locale);
        if ($message === $key) {
            throw new RuntimeException('Unknown or untranslated SMS template: '.$template);
        }

        return $this->client->send($mobile, $message);
    }
}
