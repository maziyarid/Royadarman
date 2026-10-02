<?php

declare(strict_types=1);

/**
 * Synthetic contract oracle. No database, no people, no fees, no holidays.
 * PROPOSAL behaviour, not an accepted clinic policy and not a migration.
 *
 * Run: php backend/tests/Support/SchedulingContractOracle.php
 */

require_once dirname(__DIR__, 2).'/app/Domain/Scheduling/SchedulingLifecycle.php';

use App\Domain\Scheduling\SchedulingLifecycle;

function fail(string $message): void
{
    fwrite(STDERR, "FAIL {$message}\n");
    exit(1);
}

function expect(bool $ok, string $message): void
{
    if (! $ok) {
        fail($message);
    }
}

function throws(callable $fn, string $code): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        expect($e->getMessage() === $code, "expected {$code} got ".$e->getMessage());

        return;
    }
    fail("expected {$code} but no exception");
}

function hold(SchedulingLifecycle $life, string $key, string $start, string $end, string $subject = 'subject-a', string $payment = 'none'): array
{
    return $life->placeHold([
        'observed_version' => $life->version(),
        'idempotency_key' => $key,
        'starts_at_utc' => $start,
        'ends_at_utc' => $end,
        'subject_ref' => $subject,
        'at_utc' => '2026-09-30T12:00:00Z',
        'payment_indicator' => $payment,
    ]);
}

function confirm(SchedulingLifecycle $life, string $id): array
{
    return $life->confirm([
        'observed_version' => $life->version(),
        'booking_id' => $id,
    ]);
}

$life = new SchedulingLifecycle('tenant-a', 'branch-a', 'resource-a', 1);
$first = hold($life, 'hold-1', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z');
expect($first['status'] === 'held', 'first hold');
expect($life->remaining('2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z') === 0, 'last slot taken');
throws(
    fn () => hold($life, 'hold-2', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z', 'subject-b'),
    'scheduling_capacity_exhausted',
);
expect($life->remaining('2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z') === 0, 'rejected hold did not double book');

$stale = new SchedulingLifecycle('tenant-a', 'branch-a', 'resource-a', 2);
$seen = $stale->version();
hold($stale, 'a', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z');
throws(
    fn () => $stale->placeHold([
        'observed_version' => $seen,
        'idempotency_key' => 'b',
        'starts_at_utc' => '2026-04-01T07:00:00Z',
        'ends_at_utc' => '2026-04-01T07:30:00Z',
        'subject_ref' => 'subject-b',
        'at_utc' => '2026-09-30T12:00:00Z',
    ]),
    'scheduling_stale_version',
);
expect($stale->remaining('2026-04-01T07:00:00Z', '2026-04-01T07:30:00Z') === 2, 'stale command changed nothing');

$replay = hold($life, 'hold-1', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z');
expect($replay['id'] === $first['id'], 'idempotent replay');
throws(
    fn () => hold($life, 'hold-1', '2026-04-01T08:00:00Z', '2026-04-01T08:30:00Z'),
    'scheduling_idempotency_mismatch',
);

$adjacent = hold($life, 'adjacent', '2026-04-01T07:00:00Z', '2026-04-01T07:30:00Z', 'subject-b');
expect($adjacent['status'] === 'held', 'half-open end does not overlap');

$cancelled = $life->cancel([
    'observed_version' => $life->version(),
    'booking_id' => $first['id'],
]);
expect($cancelled['status'] === 'cancelled', 'cancel');
expect($life->remaining('2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z') === 1, 'cancel releases');
$again = hold($life, 'after-cancel', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z');
expect($again['id'] !== $first['id'], 'released slot can be held once');

$paid = new SchedulingLifecycle('tenant-a', 'branch-a', 'resource-a', 1);
$paidHold = hold($paid, 'paid', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z', 'subject-a', 'recorded_elsewhere');
throws(
    fn () => $paid->expireHold(['observed_version' => $paid->version(), 'booking_id' => $paidHold['id']]),
    'scheduling_silent_release_forbidden',
);
throws(
    fn () => $paid->cancel(['observed_version' => $paid->version(), 'booking_id' => $paidHold['id']]),
    'scheduling_silent_release_forbidden',
);
expect($paid->view($paidHold['id'])['status'] === 'held', 'paid indicator still held');
$explicit = $paid->cancel([
    'observed_version' => $paid->version(),
    'booking_id' => $paidHold['id'],
    'explicit_release' => true,
]);
expect($explicit['status'] === 'cancelled', 'explicit release has no refund event');
foreach ($paid->events() as $event) {
    expect($event['sms'] === false && $event['channel'] === null, 'domain event is not an SMS');
    expect($event['name'] !== 'scheduling.refunded', 'no financial state emitted');
}

$unpaid = new SchedulingLifecycle('tenant-a', 'branch-a', 'resource-a', 1);
$unpaidHold = hold($unpaid, 'unpaid', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z');
$expired = $unpaid->expireHold(['observed_version' => $unpaid->version(), 'booking_id' => $unpaidHold['id']]);
expect($expired['status'] === 'expired', 'unrecorded hold can expire');
expect($unpaid->remaining('2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z') === 1, 'expiry releases');

$flow = new SchedulingLifecycle('tenant-a', 'branch-a', 'resource-a', 1);
$flowHold = hold($flow, 'flow', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z');
throws(fn () => $flow->arrive(['observed_version' => $flow->version(), 'booking_id' => $flowHold['id']]), 'scheduling_illegal_transition');
$confirmed = confirm($flow, $flowHold['id']);
expect($confirmed['status'] === 'confirmed', 'confirm');
throws(fn () => $flow->markNoShow(['observed_version' => $flow->version(), 'booking_id' => $flowHold['id']]), 'scheduling_policy_required');
$kept = $flow->markNoShow([
    'observed_version' => $flow->version(),
    'booking_id' => $flowHold['id'],
    'releases_capacity' => false,
]);
expect($kept['status'] === 'no_show_retained', 'retain is explicit');
expect($flow->remaining('2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z') === 0, 'retained no-show still occupies');

$releaseShow = new SchedulingLifecycle('tenant-a', 'branch-a', 'resource-a', 1);
$show = confirm($releaseShow, hold($releaseShow, 'show', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z')['id']);
$gone = $releaseShow->markNoShow([
    'observed_version' => $releaseShow->version(),
    'booking_id' => $show['id'],
    'releases_capacity' => true,
]);
expect($gone['status'] === 'no_show', 'release is explicit');
expect($releaseShow->remaining('2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z') === 1, 'released no-show frees capacity');

$move = new SchedulingLifecycle('tenant-a', 'branch-a', 'resource-a', 1);
$left = confirm($move, hold($move, 'left', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z')['id']);
$right = confirm($move, hold($move, 'right', '2026-04-01T08:00:00Z', '2026-04-01T08:30:00Z', 'subject-b')['id']);
$versionBefore = $move->version();
throws(
    fn () => $move->reschedule([
        'observed_version' => $move->version(),
        'booking_id' => $left['id'],
        'idempotency_key' => 'move-fail',
        'starts_at_utc' => '2026-04-01T08:00:00Z',
        'ends_at_utc' => '2026-04-01T08:30:00Z',
    ]),
    'scheduling_capacity_exhausted',
);
expect($move->version() === $versionBefore, 'failed reschedule version unchanged');
expect($move->view($left['id'])['status'] === 'confirmed', 'failed reschedule keeps original');
expect($move->view($left['id'])['starts_at_utc'] === '2026-04-01T06:30:00Z', 'original interval unchanged');
$moved = $move->reschedule([
    'observed_version' => $move->version(),
    'booking_id' => $left['id'],
    'idempotency_key' => 'move-ok',
    'starts_at_utc' => '2026-04-01T09:00:00Z',
    'ends_at_utc' => '2026-04-01T09:30:00Z',
]);
expect($moved['status'] === 'confirmed' && $moved['supersedes_booking_id'] === $left['id'], 'reschedule successor');
expect($move->view($left['id'])['status'] === 'rescheduled', 'predecessor released');
expect($move->remaining('2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z') === 1, 'old interval free');
expect($move->remaining('2026-04-01T09:00:00Z', '2026-04-01T09:30:00Z') === 0, 'new interval taken once');
expect($move->view($right['id'])['status'] === 'confirmed', 'unrelated booking untouched');

$wait = new SchedulingLifecycle('tenant-a', 'branch-a', 'resource-a', 1);
hold($wait, 'occupies', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z');
$remainingBefore = $wait->remaining('2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z');
$entry = $wait->enqueueWaitlist([
    'subject_ref' => 'subject-b',
    'at_utc' => '2026-09-30T12:00:00Z',
    'starts_at_utc' => '2026-04-01T06:30:00Z',
    'ends_at_utc' => '2026-04-01T07:00:00Z',
]);
expect($entry['status'] === 'waiting', 'waitlist');
expect($wait->remaining('2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z') === $remainingBefore, 'waitlist does not consume');
throws(
    fn () => $wait->promoteWaitlist([
        'waitlist_id' => $entry['id'],
        'observed_version' => $wait->version(),
        'idempotency_key' => 'promote-1',
        'at_utc' => '2026-09-30T12:00:00Z',
    ]),
    'scheduling_capacity_exhausted',
);

$restrict = new SchedulingLifecycle('tenant-a', 'branch-a', 'resource-a', 1);
throws(fn () => $restrict->addRestriction([
    'tenant_id' => '',
    'subject_ref' => 'subject-a',
    'reason_code' => 'reason-opaque',
]), 'scheduling_tenant_required');
$rule = $restrict->addRestriction([
    'tenant_id' => 'tenant-a',
    'branch_id' => null,
    'subject_ref' => 'subject-a',
    'reason_code' => 'reason-opaque',
]);
throws(
    fn () => hold($restrict, 'blocked', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z'),
    'scheduling_restriction_active',
);
$otherBranch = new SchedulingLifecycle('tenant-a', 'branch-b', 'resource-b', 1);
$otherBranch->addRestriction([
    'tenant_id' => 'tenant-a',
    'branch_id' => null,
    'subject_ref' => 'subject-a',
    'reason_code' => 'reason-opaque',
]);
throws(
    fn () => hold($otherBranch, 'blocked-b', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z'),
    'scheduling_restriction_active',
);
$otherTenant = new SchedulingLifecycle('tenant-b', 'branch-a', 'resource-a', 1);
$allowed = hold($otherTenant, 'other-tenant', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z');
expect($allowed['status'] === 'held', 'restriction does not cross tenants');
$override = $restrict->addOverride([
    'restriction_id' => $rule['id'],
    'reason_code' => 'override-opaque',
    'expires_at_utc' => '2026-10-01T00:00:00Z',
]);
expect($override['restriction_id'] === $rule['id'], 'override stored');
expect($restrict->restrictions()[0]['status'] === 'active', 'override does not delete restriction');
$overridden = hold($restrict, 'overridden', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z');
expect($overridden['status'] === 'held', 'active override allows one hold');

$branchOnly = new SchedulingLifecycle('tenant-a', 'branch-b', 'resource-b', 1);
$branchOnly->addRestriction([
    'tenant_id' => 'tenant-a',
    'branch_id' => 'branch-a',
    'subject_ref' => 'subject-a',
    'reason_code' => 'reason-opaque',
]);
$elsewhere = hold($branchOnly, 'elsewhere', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z');
expect($elsewhere['status'] === 'held', 'other branch is not globally blocked');

$ins = new SchedulingLifecycle('tenant-a', 'branch-a', 'resource-a', 1);
$ins->addRestriction([
    'tenant_id' => 'tenant-a',
    'subject_ref' => 'subject-a',
    'reason_code' => 'reason-opaque',
]);
$note = $ins->noteInsurance([
    'subject_ref' => 'subject-a',
    'provider_label' => 'label-only',
    'starts_at_utc' => '2026-04-01T06:30:00Z',
    'ends_at_utc' => '2026-04-01T07:00:00Z',
]);
expect($note['affects_eligibility'] === false, 'insurance is not eligibility');
throws(
    fn () => hold($ins, 'still-blocked', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z'),
    'scheduling_restriction_active',
);

throws(
    fn () => (new SchedulingLifecycle('tenant-a', 'branch-a', 'resource-a', 1))->placeHold([
        'observed_version' => 0,
        'idempotency_key' => 'ops',
        'calendar_kind' => 'task',
        'starts_at_utc' => '2026-04-01T06:30:00Z',
        'ends_at_utc' => '2026-04-01T07:00:00Z',
        'subject_ref' => 'subject-a',
        'at_utc' => '2026-09-30T12:00:00Z',
    ]),
    'scheduling_wrong_calendar',
);
throws(
    fn () => hold(new SchedulingLifecycle('tenant-a', 'branch-a', 'resource-a', 1), 'bad', '2026-04-01T07:00:00Z', '2026-04-01T07:00:00Z'),
    'scheduling_invalid_interval',
);

$arrived = new SchedulingLifecycle('tenant-a', 'branch-a', 'resource-a', 1);
$arrivedRow = confirm($arrived, hold($arrived, 'arr', '2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z')['id']);
$seenArrival = $arrived->arrive(['observed_version' => $arrived->version(), 'booking_id' => $arrivedRow['id']]);
expect($seenArrival['status'] === 'arrived', 'arrival');
expect($arrived->remaining('2026-04-01T06:30:00Z', '2026-04-01T07:00:00Z') === 0, 'arrived still occupies');

foreach ((new SchedulingLifecycle('tenant-a', 'branch-a', 'resource-a', 1))->events() as $event) {
    expect($event['sms'] === false, 'sms stays unset');
}

echo "PASS scheduling contract\n";
echo "SYNTHETIC_ONLY no database\n";

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    exit(0);
}
