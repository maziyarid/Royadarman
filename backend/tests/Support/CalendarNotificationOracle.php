<?php

declare(strict_types=1);

/**
 * G1.3 calendar notification correlation oracle.
 * Synthetic ids only. Throwaway database only. No SMS provider is contacted.
 * Shared Outbox.php and ProcessOutboxEvent.php are not loaded and not modified.
 */

require_once dirname(__DIR__, 2).'/app/Domain/Scheduling/CalendarNotificationPolicy.php';

use App\Domain\Scheduling\CalendarNotificationPolicy;

/** @return list<string> */
function calendar_notification_failures(PDO $pdo): array
{
    $failures = [];
    $check = static function (bool $ok, string $message) use (&$failures): void {
        if (!$ok) {
            $failures[] = $message;
        }
    };

    calendar_notification_reset($pdo);
    $sender = new CalendarNotificationFakeSender();

    $change = calendar_notification_apply_change($pdo, [
        'kind' => 'task', 'locale' => 'fa', 'case_id' => 'SYN-CASE-1', 'task_status' => 'open',
        'source_id' => 'SYN-TASK-1', 'assignee_id' => 101, 'coordinator_id' => 101,
        'due_at' => '2025-03-19 20:30:00',
    ]);
    $check($change['enqueue'] === false && $change['sms'] === false, 'a calendar row is not an SMS');
    $check($change['may_use_current_outbox_job'] === false, 'calendar change must not enter the current SMS job');
    $check($change['deep_link'] === '/fa/panel/tasks?status=open', 'task deep link matches the panel route');
    $check(!str_contains($change['deep_link'], 'token'), 'deep link has no token');
    $check(calendar_notification_count($pdo, 'outbox_events') === 0, 'calendar change wrote an outbox row');
    $check($sender->calls === 0, 'sender ran for a calendar change');

    calendar_notification_apply_change($pdo, [
        'kind' => 'task', 'locale' => 'fa', 'case_id' => 'SYN-CASE-1', 'task_status' => 'open',
        'source_id' => 'SYN-TASK-1', 'assignee_id' => 101, 'coordinator_id' => 101,
        'due_at' => '2026-03-19 20:30:00',
    ]);
    $check(calendar_notification_count($pdo, 'calendar_rows') === 1, 'schedule edit duplicated the calendar row');
    $check(calendar_notification_count($pdo, 'outbox_events') === 0, 'schedule edit created a notification');

    calendar_notification_apply_change($pdo, [
        'kind' => 'task', 'locale' => 'fa', 'case_id' => 'SYN-CASE-1', 'task_status' => 'open',
        'source_id' => 'SYN-TASK-1', 'assignee_id' => 202, 'coordinator_id' => 202,
        'due_at' => '2026-03-19 20:30:00',
    ]);
    $visible = $pdo->query('SELECT COUNT(*) FROM calendar_rows WHERE assignee_id = 101 AND coordinator_id = 101')->fetchColumn();
    $check((int) $visible === 0, 'reassignment left the row on the old coordinator');
    $check(calendar_notification_count($pdo, 'calendar_rows') === 1, 'reassignment duplicated the calendar row');
    $check($sender->calls === 0, 'reassignment sent SMS');

    calendar_notification_apply_change($pdo, [
        'kind' => 'referral_expiry', 'locale' => 'fa', 'case_id' => 'SYN-CASE-1',
        'source_id' => 'SYN-GRANT-1', 'assignee_id' => 101, 'coordinator_id' => 101,
        'due_at' => '2026-03-19 20:30:00', 'revoked' => false,
    ]);
    calendar_notification_apply_change($pdo, [
        'kind' => 'referral_expiry', 'locale' => 'fa', 'case_id' => 'SYN-CASE-1',
        'source_id' => 'SYN-GRANT-1', 'assignee_id' => 101, 'coordinator_id' => 101,
        'due_at' => '2026-03-19 20:30:00', 'revoked' => true,
    ]);
    $activeGrants = $pdo->query("SELECT COUNT(*) FROM calendar_rows WHERE kind = 'referral_expiry' AND revoked = 0")->fetchColumn();
    $check((int) $activeGrants === 0, 'revoked referral stayed on the calendar');
    $check($sender->calls === 0, 'revocation sent SMS');

    $intent = [
        'source_type' => 'coordination_task', 'source_id' => 'SYN-TASK-1', 'calendar_kind' => 'task',
        'recipient_id' => 'SYN-USER-101', 'channel' => 'sms', 'preference' => 'allow',
        'locale' => 'fa', 'case_id' => 'SYN-CASE-1', 'task_status' => 'open',
    ];
    $first = calendar_notification_record_intent($pdo, $sender, $intent);
    $second = calendar_notification_record_intent($pdo, $sender, $intent);
    $check($first['id'] === $second['id'], 'duplicate intent created a second outbox row');
    $check(calendar_notification_count($pdo, 'outbox_events') === 1, 'deduplication key was not unique');
    $check(calendar_notification_count($pdo, 'notification_deliveries') === 1, 'duplicate intent created a second delivery');
    $check($sender->calls === 1, 'duplicate job called the provider again, calls='.$sender->calls);
    $check($sender->keys === [$first['id']], 'provider idempotency key was not the outbox id');
    $check($first['may_use_current_outbox_job'] === false, 'explicit intent was marked safe for the current SMS job');

    $closed = calendar_notification_record_intent($pdo, $sender, [
        'source_type' => 'home_service_request', 'source_id' => 'SYN-HOME-1', 'calendar_kind' => 'home_service',
        'recipient_id' => 'SYN-USER-101', 'channel' => 'sms', 'preference' => 'absent',
        'locale' => 'en', 'case_id' => 'SYN-CASE-1',
    ]);
    $check($closed['status'] === 'suppressed', 'missing preference was not fail-closed');
    $check($sender->calls === 1, 'suppressed intent called the provider');
    $payload = $pdo->query('SELECT payload FROM outbox_events WHERE id = '.$pdo->quote($closed['id']))->fetchColumn();
    $check(is_string($payload) && !str_contains($payload, '0912') && !str_contains($payload, '+98'), 'payload contains a phone');

    $beforeRows = calendar_notification_count($pdo, 'calendar_rows');
    $failed = calendar_notification_record_intent($pdo, $sender, [
        'source_type' => 'referral_grant', 'source_id' => 'SYN-GRANT-2', 'calendar_kind' => 'referral_expiry',
        'recipient_id' => 'SYN-USER-101', 'channel' => 'sms', 'preference' => 'allow',
        'locale' => 'fa', 'case_id' => 'SYN-CASE-1',
    ], fail: true);
    $check($failed['status'] === 'failed', 'failed provider was not recorded as failed');
    $attempts = (int) $pdo->query('SELECT attempts FROM outbox_events WHERE id = '.$pdo->quote($failed['id']))->fetchColumn();
    $check($attempts === 1, 'failed attempt was not counted');
    $retried = calendar_notification_retry($pdo, $sender, $failed['id']);
    $check($retried['status'] === 'sent', 'retry did not send');
    $check(calendar_notification_count($pdo, 'notification_deliveries') === 3, 'retry inserted another delivery');
    $attemptsAfter = (int) $pdo->query('SELECT attempts FROM outbox_events WHERE id = '.$pdo->quote($failed['id']))->fetchColumn();
    $check($attemptsAfter === 2, 'retry did not increment the same outbox row');
    $check(calendar_notification_count($pdo, 'calendar_rows') === $beforeRows, 'delivery failure changed the calendar');

    $threw = false;
    try {
        CalendarNotificationPolicy::forExplicitIntent(array_replace($intent, ['recipient_id' => '+989121234567']));
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    $check($threw, 'a phone number was accepted as a recipient id');

    $threw = false;
    try {
        CalendarNotificationPolicy::deepLink('fa', 'task', 'SYN-CASE-1', 'open');
        CalendarNotificationPolicy::deepLink('fa', 'home_service', 'case?token=abc', null);
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    $check($threw, 'a token was accepted in the deep link');

    $callsBefore = $sender->calls;
    $outboxBefore = calendar_notification_count($pdo, 'outbox_events');
    $deliveryBefore = calendar_notification_count($pdo, 'notification_deliveries');
    $moved = calendar_notification_record_intent($pdo, $sender, array_replace($intent, [
        'occurs_at' => '2026-03-19 20:30:00',
    ]));
    $movedAgain = calendar_notification_record_intent($pdo, $sender, array_replace($intent, [
        'occurs_at' => '2026-03-19 20:30:00',
    ]));
    $later = calendar_notification_record_intent($pdo, $sender, array_replace($intent, [
        'occurs_at' => '2026-03-20 20:30:00',
    ]));
    $silent = calendar_notification_record_intent($pdo, $sender, array_replace($intent, [
        'occurs_at' => '2026-03-21 20:30:00',
        'preference' => 'suppress',
    ]));
    $check($moved['id'] === $movedAgain['id'], 'same UTC instant was not idempotent');
    $check($moved['id'] !== $first['id'], 'timed intent collided with the untimed intent');
    $check($later['id'] !== $moved['id'], 'a new instant reused the previous notification');
    $check($silent['status'] === 'suppressed', 'suppressed reschedule was sent');
    $check($sender->calls === $callsBefore + 2, 'reschedule provider calls='.$sender->calls);
    $check(calendar_notification_count($pdo, 'outbox_events') === $outboxBefore + 3, 'reschedule outbox count drifted');
    $check(calendar_notification_count($pdo, 'notification_deliveries') === $deliveryBefore + 3, 'reschedule delivery count drifted');
    $laterPayload = (string) $pdo->query('SELECT payload FROM outbox_events WHERE id = '.$pdo->quote($later['id']))->fetchColumn();
    $movedPayload = (string) $pdo->query('SELECT payload FROM outbox_events WHERE id = '.$pdo->quote($moved['id']))->fetchColumn();
    $check(str_contains($laterPayload, '2026-03-20 20:30:00') && !str_contains($movedPayload, '2026-03-20 20:30:00'), 'payload kept the old instant');

    $threw = false;
    try {
        CalendarNotificationPolicy::forExplicitIntent(array_replace($intent, ['occurs_at' => '2026-02-31 00:00:00']));
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    $check($threw, 'an impossible UTC timestamp was accepted');
    $threw = false;
    try {
        CalendarNotificationPolicy::forExplicitIntent(array_replace($intent, ['occurs_at' => '2022-03-22 00:30:00+03:30']));
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    $check($threw, 'a zoned local timestamp was accepted as UTC');

    return $failures;
}

/** @param array<string, mixed> $change @return array<string, mixed> */
function calendar_notification_apply_change(PDO $pdo, array $change): array
{
    $decision = CalendarNotificationPolicy::forCalendarChange($change);
    $statement = $pdo->prepare('INSERT INTO calendar_rows (source_id, kind, case_id, assignee_id, coordinator_id, due_at, revoked) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE kind = VALUES(kind), case_id = VALUES(case_id), assignee_id = VALUES(assignee_id), coordinator_id = VALUES(coordinator_id), due_at = VALUES(due_at), revoked = VALUES(revoked)');
    $statement->execute([
        $change['source_id'], $change['kind'], $change['case_id'], $change['assignee_id'], $change['coordinator_id'],
        $change['due_at'], !empty($change['revoked']) ? 1 : 0,
    ]);
    if ($decision['enqueue'] !== false) {
        throw new RuntimeException('Calendar change tried to enqueue.');
    }

    return $decision;
}

/** @param array<string, mixed> $command @return array<string, mixed> */
function calendar_notification_record_intent(PDO $pdo, CalendarNotificationFakeSender $sender, array $command, bool $fail = false): array
{
    $decision = CalendarNotificationPolicy::forExplicitIntent($command);
    if ($decision['may_use_current_outbox_job'] !== false) {
        throw new RuntimeException('Policy tried to use the current SMS job.');
    }
    $existing = $pdo->prepare('SELECT id FROM outbox_events WHERE deduplication_key = ?');
    $existing->execute([$decision['deduplication_key']]);
    $id = $existing->fetchColumn();
    if ($id === false) {
        $id = calendar_notification_ulid();
        $body = [
            'calendar_kind' => $command['calendar_kind'],
            'source_type' => $command['source_type'],
            'source_id' => $command['source_id'],
            'recipient_id' => $command['recipient_id'],
            'deep_link' => $decision['deep_link'],
            'template_key' => 'calendar_explicit_notice',
        ];
        if (array_key_exists('occurs_at', $command) && $command['occurs_at'] !== null) {
            $body['occurs_at'] = (string) $command['occurs_at'];
        }
        $payload = json_encode($body, JSON_THROW_ON_ERROR);
        $insert = $pdo->prepare('INSERT INTO outbox_events (id, event_type, aggregate_type, aggregate_id, recipient_locale, payload, deduplication_key, attempts, available_at, processed_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, UTC_TIMESTAMP(), NULL)');
        $insert->execute([$id, 'calendar.notify', $command['source_type'], $command['source_id'], $command['locale'], $payload, $decision['deduplication_key']]);
        $delivery = $pdo->prepare('INSERT INTO notification_deliveries (id, outbox_event_id, channel, provider_reference, status, recipient_locale, template_key, failure_code, created_at) VALUES (?, ?, ?, NULL, ?, ?, ?, NULL, UTC_TIMESTAMP())');
        $delivery->execute([
            calendar_notification_ulid(), $id, $decision['status'] === 'suppressed' ? 'none' : 'sms',
            $decision['status'], $command['locale'], 'calendar_explicit_notice',
        ]);
    }
    if ($decision['status'] === 'sending') {
        calendar_notification_dispatch($pdo, $sender, (string) $id, $fail);
    }
    $status = $pdo->query('SELECT status FROM notification_deliveries WHERE outbox_event_id = '.$pdo->quote((string) $id))->fetchColumn();

    return ['id' => (string) $id, 'status' => (string) $status] + $decision;
}

function calendar_notification_dispatch(PDO $pdo, CalendarNotificationFakeSender $sender, string $id, bool $fail): void
{
    $row = $pdo->query('SELECT processed_at, payload, recipient_locale FROM outbox_events WHERE id = '.$pdo->quote($id))->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['processed_at'] !== null) {
        return;
    }
    $delivery = $pdo->query('SELECT id, status FROM notification_deliveries WHERE outbox_event_id = '.$pdo->quote($id))->fetch(PDO::FETCH_ASSOC);
    if (!$delivery || $delivery['status'] === 'sent' || $delivery['status'] === 'suppressed') {
        return;
    }
    if ($fail) {
        $pdo->prepare('UPDATE notification_deliveries SET status = ?, failure_code = ? WHERE id = ?')->execute(['failed', 'FakeProviderDown', $delivery['id']]);
        $pdo->prepare('UPDATE outbox_events SET attempts = attempts + 1 WHERE id = ?')->execute([$id]);

        return;
    }
    $payload = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
    $sender->send('SYN-NOT-A-PHONE', (string) $payload['template_key'], (string) $row['recipient_locale'], ['deep_link' => $payload['deep_link']], $id);
    $pdo->prepare('UPDATE notification_deliveries SET status = ?, provider_reference = ? WHERE id = ?')->execute(['sent', 'fake-'.$id, $delivery['id']]);
    $pdo->prepare('UPDATE outbox_events SET attempts = attempts + 1, processed_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
}

/** @return array{status:string} */
function calendar_notification_retry(PDO $pdo, CalendarNotificationFakeSender $sender, string $id): array
{
    calendar_notification_dispatch($pdo, $sender, $id, false);
    $status = $pdo->query('SELECT status FROM notification_deliveries WHERE outbox_event_id = '.$pdo->quote($id))->fetchColumn();

    return ['status' => (string) $status];
}

function calendar_notification_reset(PDO $pdo): void
{
    $pdo->exec('DROP TABLE IF EXISTS notification_deliveries');
    $pdo->exec('DROP TABLE IF EXISTS outbox_events');
    $pdo->exec('DROP TABLE IF EXISTS calendar_rows');
    $pdo->exec('CREATE TABLE calendar_rows (source_id VARCHAR(64) PRIMARY KEY, kind VARCHAR(32) NOT NULL, case_id VARCHAR(64) NOT NULL, assignee_id INT NOT NULL, coordinator_id INT NOT NULL, due_at TIMESTAMP NULL, revoked TINYINT NOT NULL DEFAULT 0)');
    $pdo->exec('CREATE TABLE outbox_events (id CHAR(26) PRIMARY KEY, event_type VARCHAR(100) NOT NULL, aggregate_type VARCHAR(100) NOT NULL, aggregate_id VARCHAR(64) NOT NULL, recipient_locale VARCHAR(5) NULL, payload JSON NOT NULL, deduplication_key VARCHAR(160) NOT NULL, attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, available_at TIMESTAMP NOT NULL, processed_at TIMESTAMP NULL, UNIQUE KEY outbox_dedup (deduplication_key))');
    $pdo->exec('CREATE TABLE notification_deliveries (id CHAR(26) PRIMARY KEY, outbox_event_id CHAR(26) NOT NULL, channel VARCHAR(24) NOT NULL, provider_reference VARCHAR(120) NULL, status VARCHAR(24) NOT NULL, recipient_locale VARCHAR(5) NOT NULL, template_key VARCHAR(100) NOT NULL, failure_code VARCHAR(80) NULL, created_at TIMESTAMP NOT NULL, UNIQUE KEY delivery_once (outbox_event_id, channel))');
}

function calendar_notification_count(PDO $pdo, string $table): int
{
    if (!in_array($table, ['calendar_rows', 'outbox_events', 'notification_deliveries'], true)) {
        throw new InvalidArgumentException('Unknown table');
    }

    return (int) $pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
}

function calendar_notification_ulid(): string
{
    $time = str_pad(base_convert((string) (int) (microtime(true) * 1000), 10, 32), 10, '0', STR_PAD_LEFT);
    $random = strtoupper(bin2hex(random_bytes(8)));

    return substr(strtoupper($time).$random, 0, 26);
}

final class CalendarNotificationFakeSender
{
    public int $calls = 0;

    /** @var list<string> */
    public array $keys = [];

    /** @param array<string, scalar|null> $parameters */
    public function send(string $mobile, string $template, string $locale, array $parameters, string $idempotencyKey): string
    {
        if (preg_match('/\d{8,}/', $mobile)) {
            throw new RuntimeException('Fake sender was given a phone number.');
        }
        $this->calls++;
        $this->keys[] = $idempotencyKey;

        return 'fake-'.$idempotencyKey;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? ($argv[0] ?? '')) === __FILE__) {
    $dsn = $argv[1] ?? '';
    if (!str_contains($dsn, 'g13_synthetic') || str_contains($dsn, '3306')) {
        fwrite(STDERR, "REFUSED dsn\n");
        exit(2);
    }
    $pdo = new PDO($dsn, $argv[2] ?? 'root', $argv[3] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("SET time_zone = '+00:00'");
    $failures = calendar_notification_failures($pdo);
    if ($failures !== []) {
        fwrite(STDERR, implode("\n", $failures)."\n");
        fwrite(STDOUT, "FAIL calendar notification\nORACLE_EXIT 1\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS calendar notification\nSYNTHETIC_ONLY throwaway database\nORACLE_EXIT 0\n");
}
