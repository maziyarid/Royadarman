<?php

namespace App\Domain\Operations\Contracts;

interface NotificationSender
{
    /** @param array<string, scalar|null> $parameters */
    public function send(string $mobile, string $template, string $locale, array $parameters, string $idempotencyKey): string;
}
