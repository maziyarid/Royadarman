<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use InvalidArgumentException;

/**
 * Shared operations-calendar document for web, Android, and iOS.
 *
 * The server already chose the half-open UTC window. A client must not send a
 * month length or a replacement window. This class does not convert Jalali
 * dates, query a database, or open a route. Clinic bookings are a different
 * calendar and are rejected.
 */
final class OperationsCalendarProjection
{
    public const MEDIA_TYPE = 'application/vnd.royadarman.operations-calendar.v1+json';

    public const KINDS = ['task', 'home_service', 'referral_expiry'];

    /**
     * @param array{start_utc:string,end_utc:string} $serverWindow
     * @param list<array<string, mixed>> $events
     * @param array<string, mixed>|null $clientHint
     * @return array<string, mixed>
     */
    public static function project(array $serverWindow, array $events, ?array $clientHint = null): array
    {
        self::rejectClientCorrection($clientHint);
        $start = self::instant((string) ($serverWindow['start_utc'] ?? ''));
        $end = self::instant((string) ($serverWindow['end_utc'] ?? ''));
        if ($end <= $start) {
            throw new InvalidArgumentException('invalid_window');
        }

        $seen = [];
        $included = [];
        $excluded = 0;
        foreach ($events as $event) {
            if (!is_array($event)) {
                throw new InvalidArgumentException('invalid_event');
            }
            $kind = (string) ($event['kind'] ?? '');
            if (!in_array($kind, self::KINDS, true)) {
                throw new InvalidArgumentException('scheduling_wrong_calendar');
            }
            $id = (string) ($event['id'] ?? '');
            self::assertOpaqueId($id);
            $at = self::instant((string) ($event['at_utc'] ?? ''));
            $status = (string) ($event['status'] ?? '');
            if (!preg_match('/^[a-z_]{1,32}$/', $status)) {
                throw new InvalidArgumentException('invalid_event');
            }
            $url = self::url((string) ($event['url'] ?? ''));
            foreach ($event as $key => $value) {
                if (in_array((string) $key, ['phone', 'mobile', 'national_id', 'token', 'body', 'payload'], true)) {
                    throw new InvalidArgumentException('secret_field');
                }
                if (is_string($value)) {
                    self::rejectSecret((string) $value);
                }
            }
            if (isset($seen[$id])) {
                throw new InvalidArgumentException('duplicate_event');
            }
            $seen[$id] = true;
            if ($at < $start || $at >= $end) {
                $excluded++;
                continue;
            }
            $included[] = [
                'id' => $id,
                'kind' => $kind,
                'at_utc' => self::format($at),
                'status' => $status,
                'url' => $url,
            ];
        }

        return [
            'media_type' => self::MEDIA_TYPE,
            'time_zone' => 'Asia/Tehran',
            'window' => [
                'start_utc' => self::format($start),
                'end_utc' => self::format($end),
                'half_open' => true,
            ],
            'client_must_not_recompute_bounds' => true,
            'excluded_outside_window' => $excluded,
            'events' => $included,
        ];
    }

    /** @param array<string, mixed>|null $clientHint */
    private static function rejectClientCorrection(?array $clientHint): void
    {
        if ($clientHint === null || $clientHint === []) {
            return;
        }
        if (array_key_exists('month_length', $clientHint) || array_key_exists('window', $clientHint) || array_key_exists('jmonth', $clientHint)) {
            throw new InvalidArgumentException('client_must_not_recompute_bounds');
        }
        if (($clientHint['calendar'] ?? null) !== null) {
            throw new InvalidArgumentException('scheduling_wrong_calendar');
        }
    }

    private static function instant(string $value): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$parsed || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
            throw new InvalidArgumentException('invalid_instant');
        }

        return $parsed;
    }

    private static function format(\DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function assertOpaqueId(string $id): void
    {
        if (!preg_match('/^[A-Za-z0-9-]{1,64}$/', $id)) {
            throw new InvalidArgumentException('invalid_event');
        }
    }

    private static function url(string $url): string
    {
        self::rejectSecret($url);
        if (!preg_match('#^/(fa|en|ar)/panel/(tasks\?status=[a-z_]{1,32}|cases/[A-Za-z0-9-]{1,64})$#', $url)) {
            throw new InvalidArgumentException('invalid_deep_link');
        }

        return $url;
    }

    private static function rejectSecret(string $value): void
    {
        if (preg_match('/token|secret|\+98|\b09\d{9}\b/i', $value)) {
            throw new InvalidArgumentException('secret_field');
        }
    }
}
