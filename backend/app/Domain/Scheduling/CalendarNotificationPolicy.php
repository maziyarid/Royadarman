<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use InvalidArgumentException;

/**
 * Correlation rules for operations-calendar changes versus notification intents.
 *
 * The current ProcessOutboxEvent job always inserts channel "sms" and calls the
 * provider when the aggregate is a patient case with a phone. This policy does
 * not call that job. A calendar projection is not an SMS.
 */
final class CalendarNotificationPolicy
{
    public const CALENDAR_KINDS = ['task', 'home_service', 'referral_expiry'];

    /** @param array{kind:string,locale:string,case_id:string,task_status?:string|null} $change
     * @return array{enqueue:false,sms:false,channel:null,reason:string,deduplication_key:null,deep_link:string,may_use_current_outbox_job:false}
     */
    public static function forCalendarChange(array $change): array
    {
        $kind = (string) ($change['kind'] ?? '');
        if (!in_array($kind, self::CALENDAR_KINDS, true)) {
            throw new InvalidArgumentException('Unknown calendar kind.');
        }

        return [
            'enqueue' => false,
            'sms' => false,
            'channel' => null,
            'reason' => 'calendar_projection_is_not_a_notification',
            'deduplication_key' => null,
            'deep_link' => self::deepLink(
                (string) ($change['locale'] ?? ''),
                $kind,
                (string) ($change['case_id'] ?? ''),
                isset($change['task_status']) ? (string) $change['task_status'] : null,
            ),
            'may_use_current_outbox_job' => false,
        ];
    }

    /**
     * An explicit notify command is the only way a calendar source becomes an intent.
     * Preference defaults closed. The phone number is never part of this decision.
     *
     * @param array{source_type:string,source_id:string,calendar_kind:string,recipient_id:string,channel?:string|null,preference?:string|null,locale:string,case_id:string,task_status?:string|null} $command
     * @return array{enqueue:bool,sms:bool,channel:?string,reason:string,deduplication_key:?string,deep_link:string,may_use_current_outbox_job:false,status:string}
     */
    public static function forExplicitIntent(array $command): array
    {
        $kind = (string) ($command['calendar_kind'] ?? '');
        if (!in_array($kind, self::CALENDAR_KINDS, true)) {
            throw new InvalidArgumentException('Unknown calendar kind.');
        }
        $sourceType = (string) ($command['source_type'] ?? '');
        $sourceId = (string) ($command['source_id'] ?? '');
        if (!in_array($sourceType, ['coordination_task', 'home_service_request', 'referral_grant'], true)) {
            throw new InvalidArgumentException('Unknown source type.');
        }
        self::assertOpaqueId($sourceId, 'source_id');
        $recipient = (string) ($command['recipient_id'] ?? '');
        self::assertRecipientId($recipient);
        $channel = $command['channel'] ?? null;
        if ($channel !== null && $channel !== 'sms') {
            throw new InvalidArgumentException('Undeclared channel.');
        }
        $preference = (string) ($command['preference'] ?? 'absent');
        if (!in_array($preference, ['allow', 'suppress', 'absent'], true)) {
            throw new InvalidArgumentException('Unknown preference.');
        }
        $deepLink = self::deepLink(
            (string) ($command['locale'] ?? ''),
            $kind,
            (string) ($command['case_id'] ?? ''),
            isset($command['task_status']) ? (string) $command['task_status'] : null,
        );
        $key = 'calendar.notify.'.$sourceType.'.'.$sourceId.'.'.($channel ?? 'none').'.'.$recipient;
        if (array_key_exists('occurs_at', $command) && $command['occurs_at'] !== null) {
            $key .= '.'.self::utcInstantToken((string) $command['occurs_at']);
        }
        if (strlen($key) > 160) {
            throw new InvalidArgumentException('Deduplication key exceeds the outbox column.');
        }
        $send = $preference === 'allow' && $channel === 'sms';

        return [
            'enqueue' => true,
            'sms' => $send,
            'channel' => $send ? 'sms' : null,
            'reason' => $send ? 'explicit_intent_allowed' : 'preference_closed',
            'deduplication_key' => $key,
            'deep_link' => $deepLink,
            'may_use_current_outbox_job' => false,
            'status' => $send ? 'sending' : 'suppressed',
        ];
    }

    public static function deepLink(string $locale, string $kind, string $caseId, ?string $taskStatus): string
    {
        if (!in_array($locale, ['fa', 'en', 'ar'], true)) {
            throw new InvalidArgumentException('Unsupported locale.');
        }
        self::assertOpaqueId($caseId, 'case_id');
        if ($kind === 'task') {
            $status = $taskStatus ?? 'open';
            if (!preg_match('/^[a-z_]{1,32}$/', $status)) {
                throw new InvalidArgumentException('Invalid task status.');
            }

            return '/'.$locale.'/panel/tasks?status='.$status;
        }

        return '/'.$locale.'/panel/cases/'.$caseId;
    }

    private static function assertOpaqueId(string $id, string $label): void
    {
        if (!preg_match('/^[A-Za-z0-9-]{1,64}$/', $id)) {
            throw new InvalidArgumentException('Invalid '.$label.'.');
        }
        if (preg_match('/token|secret|phone/i', $id)) {
            throw new InvalidArgumentException($label.' must not carry a secret.');
        }
    }

    private static function utcInstantToken(string $value): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        $warnings = is_array($errors) ? (int) $errors['warning_count'] : 0;
        $errorCount = is_array($errors) ? (int) $errors['error_count'] : 0;
        if (!$parsed instanceof \DateTimeImmutable || $warnings > 0 || $errorCount > 0 || $parsed->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException('occurs_at must be a UTC timestamp.');
        }

        return $parsed->format('Ymd\THis\Z');
    }

    private static function assertRecipientId(string $recipient): void
    {
        self::assertOpaqueId($recipient, 'recipient_id');
        $digits = preg_replace('/\D+/', '', $recipient) ?? '';
        if (strlen($digits) >= 8 || str_starts_with($recipient, '+')) {
            throw new InvalidArgumentException('Recipient must be an id, not a phone number.');
        }
    }
}
