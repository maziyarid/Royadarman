<?php

namespace App\Domain\Operations\Exceptions;

use RuntimeException;

final class SmsDeliveryException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('SMS delivery is not configured.');
    }

    public static function providerFailed(string $provider, string $detail): self
    {
        return new self("SMS provider [{$provider}] failed: {$detail}");
    }

    public static function quotaExceeded(string $provider): self
    {
        return new self("SMS provider [{$provider}] quota exceeded.");
    }
}
