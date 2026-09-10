<?php

namespace App\Domain\Operations\Contracts;

use App\Domain\Operations\Exceptions\SmsDeliveryException;

interface SmsProvider
{
    /**
     * Send an SMS message and return the provider reference id.
     *
     * @param  array<string, scalar|null>  $parameters
     *
     * @throws SmsDeliveryException
     */
    public function send(string $mobile, string $template, string $locale, array $parameters, string $idempotencyKey): string;

    /**
     * Verify an inbound delivery callback signature.
     *
     * @param  array<string, string>  $headers  Lowercased header name => value
     */
    public function verifyCallback(string $rawBody, array $headers): bool;

    /**
     * Extract the provider reference and delivery status from a callback payload.
     *
     * @return array{reference: string, status: string, failure_code: ?string}
     */
    public function parseCallback(string $rawBody, array $headers): array;
}
