<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use InvalidArgumentException;
use RuntimeException;

/**
 * PROPOSAL ONLY — in-memory clinic-booking lifecycle for contract tests.
 *
 * Not a migration, Eloquent model, route, or production service.
 * Not the coordinator operations calendar (CoordinationTask,
 * HomeServiceRequest, ReferralGrant). Not a holiday calendar, fee table,
 * practitioner roster, merchant account, or clinical record.
 *
 * Grok 2 owns shared schema integration. Codex owns reception screens.
 * G1.5 must not copy this class into the app until tenant schema and
 * booking/cancellation/merchant policy prerequisites exist.
 *
 * Persistence equivalent (not created here): lock the resource-day row
 * with SELECT ... FOR UPDATE in the same transaction as the insert.
 * observed_version is the testable stand-in, not a substitute for that lock.
 * Capacity greater than one cannot be enforced by a unique start time alone.
 */
final class SchedulingLifecycle
{
    /** Statuses that occupy capacity. no_show_retained is an explicit policy choice. */
    public const CONSUMING = ['held', 'confirmed', 'arrived', 'no_show_retained'];

    public const OPERATIONS_CALENDAR_KINDS = ['task', 'home_service', 'referral_expiry'];

    public const PAYMENT_INDICATORS = ['none', 'required_unresolved', 'recorded_elsewhere'];

    /** @var array<string, array<string, mixed>> */
    private array $bookings = [];

    /** @var array<string, string> */
    private array $idempotency = [];

    /** @var array<string, string> */
    private array $idempotencyBody = [];

    /** @var list<array<string, mixed>> */
    private array $restrictions = [];

    /** @var list<array<string, mixed>> */
    private array $overrides = [];

    /** @var list<array<string, mixed>> */
    private array $waitlist = [];

    /** @var list<array<string, mixed>> */
    private array $insurance = [];

    /** @var list<array<string, mixed>> */
    private array $events = [];

    private int $version = 0;

    private int $seq = 0;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $branchId,
        public readonly string $resourceId,
        public readonly int $capacity,
    ) {
        if ($this->tenantId === '' || $this->branchId === '' || $this->resourceId === '' || $this->capacity < 1) {
            throw new InvalidArgumentException('scheduling_tenant_required');
        }
    }

    public function view(string $id): array
    {
        return $this->publicBooking($this->booking($id));
    }

    public function version(): int
    {
        return $this->version;
    }

    public function remaining(string $start, string $end): int
    {
        $this->assertInterval($start, $end);

        return $this->capacity - $this->consumingCount($start, $end, null);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function restrictions(): array
    {
        return $this->restrictions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function insuranceNotes(): array
    {
        return $this->insurance;
    }

    /**
     * @param array<string, mixed> $cmd
     * @return array<string, mixed>
     */
    public function placeHold(array $cmd): array
    {
        $this->rejectOperationsCalendar($cmd);
        $key = $this->requireString($cmd, 'idempotency_key');
        $body = $this->bodyHash($cmd);
        if (isset($this->idempotency[$key])) {
            if ($this->idempotencyBody[$key] !== $body) {
                throw new RuntimeException('scheduling_idempotency_mismatch');
            }

            return $this->publicBooking($this->bookings[$this->idempotency[$key]]);
        }

        $this->assertVersion($cmd);
        $start = $this->instant($cmd, 'starts_at_utc');
        $end = $this->instant($cmd, 'ends_at_utc');
        $this->assertInterval($start, $end);
        $subject = $this->requireString($cmd, 'subject_ref');
        $at = $this->instant($cmd, 'at_utc');
        $this->assertNotRestricted($subject, $at);
        if ($this->consumingCount($start, $end, null) >= $this->capacity) {
            throw new RuntimeException('scheduling_capacity_exhausted');
        }

        $id = $this->nextId('bok');
        $row = [
            'id' => $id,
            'status' => 'held',
            'starts_at_utc' => $start,
            'ends_at_utc' => $end,
            'subject_ref' => $subject,
            'payment_indicator' => $this->payment($cmd),
            'idempotency_key' => $key,
            'supersedes_booking_id' => null,
        ];
        $this->bookings[$id] = $row;
        $this->idempotency[$key] = $id;
        $this->idempotencyBody[$key] = $body;
        $this->version++;
        $this->emit('scheduling.hold_placed', $id);

        return $this->publicBooking($row);
    }

    /**
     * @param array<string, mixed> $cmd
     * @return array<string, mixed>
     */
    public function confirm(array $cmd): array
    {
        $row = $this->requireTransition($cmd, ['held'], 'confirmed');
        $this->emit('scheduling.booking_confirmed', $row['id']);

        return $this->publicBooking($row);
    }

    /**
     * @param array<string, mixed> $cmd
     * @return array<string, mixed>
     */
    public function arrive(array $cmd): array
    {
        $row = $this->requireTransition($cmd, ['confirmed'], 'arrived');
        $this->emit('scheduling.arrival_recorded', $row['id']);

        return $this->publicBooking($row);
    }

    /**
     * @param array<string, mixed> $cmd
     * @return array<string, mixed>
     */
    public function cancel(array $cmd): array
    {
        $this->assertVersion($cmd);
        $row = $this->booking($this->requireString($cmd, 'booking_id'));
        if (! in_array($row['status'], ['held', 'confirmed', 'arrived', 'no_show_retained'], true)) {
            throw new RuntimeException('scheduling_illegal_transition');
        }
        if ($row['payment_indicator'] === 'recorded_elsewhere' && ($cmd['explicit_release'] ?? false) !== true) {
            throw new RuntimeException('scheduling_silent_release_forbidden');
        }
        $row['status'] = 'cancelled';
        $this->bookings[$row['id']] = $row;
        $this->version++;
        $this->emit('scheduling.booking_cancelled', $row['id']);

        return $this->publicBooking($row);
    }

    /**
     * @param array<string, mixed> $cmd
     * @return array<string, mixed>
     */
    public function expireHold(array $cmd): array
    {
        $this->assertVersion($cmd);
        $row = $this->booking($this->requireString($cmd, 'booking_id'));
        if ($row['status'] !== 'held') {
            throw new RuntimeException('scheduling_illegal_transition');
        }
        if ($row['payment_indicator'] === 'recorded_elsewhere') {
            throw new RuntimeException('scheduling_silent_release_forbidden');
        }
        $row['status'] = 'expired';
        $this->bookings[$row['id']] = $row;
        $this->version++;
        $this->emit('scheduling.hold_expired', $row['id']);

        return $this->publicBooking($row);
    }

    /**
     * No default. releases_capacity must be boolean. Neither value is an accepted clinic policy.
     *
     * @param array<string, mixed> $cmd
     * @return array<string, mixed>
     */
    public function markNoShow(array $cmd): array
    {
        if (! array_key_exists('releases_capacity', $cmd) || ! is_bool($cmd['releases_capacity'])) {
            throw new InvalidArgumentException('scheduling_policy_required');
        }
        $status = $cmd['releases_capacity'] ? 'no_show' : 'no_show_retained';
        $row = $this->requireTransition($cmd, ['confirmed'], $status);
        $this->emit('scheduling.no_show_recorded', $row['id']);

        return $this->publicBooking($row);
    }

    /**
     * @param array<string, mixed> $cmd
     * @return array<string, mixed>
     */
    public function reschedule(array $cmd): array
    {
        $key = $this->requireString($cmd, 'idempotency_key');
        $body = $this->bodyHash($cmd);
        if (isset($this->idempotency[$key])) {
            if ($this->idempotencyBody[$key] !== $body) {
                throw new RuntimeException('scheduling_idempotency_mismatch');
            }

            return $this->publicBooking($this->bookings[$this->idempotency[$key]]);
        }

        $this->assertVersion($cmd);
        $old = $this->booking($this->requireString($cmd, 'booking_id'));
        if ($old['status'] !== 'confirmed') {
            throw new RuntimeException('scheduling_illegal_transition');
        }
        $start = $this->instant($cmd, 'starts_at_utc');
        $end = $this->instant($cmd, 'ends_at_utc');
        $this->assertInterval($start, $end);
        if ($start === $old['starts_at_utc'] && $end === $old['ends_at_utc']) {
            return $this->publicBooking($old);
        }
        if ($this->consumingCount($start, $end, $old['id']) >= $this->capacity) {
            throw new RuntimeException('scheduling_capacity_exhausted');
        }

        $old['status'] = 'rescheduled';
        $this->bookings[$old['id']] = $old;
        $id = $this->nextId('bok');
        $row = [
            'id' => $id,
            'status' => 'confirmed',
            'starts_at_utc' => $start,
            'ends_at_utc' => $end,
            'subject_ref' => $old['subject_ref'],
            'payment_indicator' => $old['payment_indicator'],
            'idempotency_key' => $key,
            'supersedes_booking_id' => $old['id'],
        ];
        $this->bookings[$id] = $row;
        $this->idempotency[$key] = $id;
        $this->idempotencyBody[$key] = $body;
        $this->version++;
        $this->emit('scheduling.booking_rescheduled', $id);

        return $this->publicBooking($row);
    }

    /**
     * @param array<string, mixed> $cmd
     * @return array<string, mixed>
     */
    public function enqueueWaitlist(array $cmd): array
    {
        $this->rejectOperationsCalendar($cmd);
        $subject = $this->requireString($cmd, 'subject_ref');
        $at = $this->instant($cmd, 'at_utc');
        $this->assertNotRestricted($subject, $at);
        $before = $this->consumingCount(
            $this->instant($cmd, 'starts_at_utc'),
            $this->instant($cmd, 'ends_at_utc'),
            null,
        );
        $id = $this->nextId('wl');
        $entry = [
            'id' => $id,
            'status' => 'waiting',
            'subject_ref' => $subject,
            'starts_at_utc' => $this->instant($cmd, 'starts_at_utc'),
            'ends_at_utc' => $this->instant($cmd, 'ends_at_utc'),
        ];
        $this->waitlist[] = $entry;
        if ($this->consumingCount($entry['starts_at_utc'], $entry['ends_at_utc'], null) !== $before) {
            throw new RuntimeException('scheduling_waitlist_consumed_capacity');
        }
        $this->emit('scheduling.waitlist_enqueued', $id);

        return $entry;
    }

    /**
     * @param array<string, mixed> $cmd
     * @return array<string, mixed>
     */
    public function promoteWaitlist(array $cmd): array
    {
        $waitId = $this->requireString($cmd, 'waitlist_id');
        $index = null;
        foreach ($this->waitlist as $i => $entry) {
            if ($entry['id'] === $waitId) {
                $index = $i;
                break;
            }
        }
        if ($index === null || $this->waitlist[$index]['status'] !== 'waiting') {
            throw new RuntimeException('scheduling_not_found');
        }
        $entry = $this->waitlist[$index];
        $hold = $this->placeHold([
            'observed_version' => $cmd['observed_version'] ?? null,
            'idempotency_key' => $this->requireString($cmd, 'idempotency_key'),
            'starts_at_utc' => $entry['starts_at_utc'],
            'ends_at_utc' => $entry['ends_at_utc'],
            'subject_ref' => $entry['subject_ref'],
            'at_utc' => $this->instant($cmd, 'at_utc'),
            'payment_indicator' => $cmd['payment_indicator'] ?? 'none',
        ]);
        $this->waitlist[$index]['status'] = 'promoted';
        $this->waitlist[$index]['booking_id'] = $hold['id'];

        return $hold;
    }

    /**
     * @param array<string, mixed> $cmd
     * @return array<string, mixed>
     */
    public function addRestriction(array $cmd): array
    {
        $tenant = $cmd['tenant_id'] ?? null;
        if (! is_string($tenant) || $tenant === '') {
            throw new InvalidArgumentException('scheduling_tenant_required');
        }
        if ($tenant !== $this->tenantId) {
            throw new InvalidArgumentException('scheduling_tenant_required');
        }
        $branch = $cmd['branch_id'] ?? null;
        if ($branch !== null && ! is_string($branch)) {
            throw new InvalidArgumentException('scheduling_tenant_required');
        }
        $reason = $this->requireString($cmd, 'reason_code');
        $row = [
            'id' => $this->nextId('rst'),
            'tenant_id' => $tenant,
            'branch_id' => $branch,
            'subject_ref' => $this->requireString($cmd, 'subject_ref'),
            'reason_code' => $reason,
            'status' => 'active',
            'expires_at_utc' => isset($cmd['expires_at_utc']) ? $this->mustInstant((string) $cmd['expires_at_utc']) : null,
        ];
        $this->restrictions[] = $row;
        $this->emit('scheduling.restriction_recorded', $row['id']);

        return $row;
    }

    /**
     * @param array<string, mixed> $cmd
     * @return array<string, mixed>
     */
    public function addOverride(array $cmd): array
    {
        $restrictionId = $this->requireString($cmd, 'restriction_id');
        $found = false;
        foreach ($this->restrictions as $restriction) {
            if ($restriction['id'] === $restrictionId && $restriction['status'] === 'active') {
                $found = true;
            }
        }
        if (! $found) {
            throw new RuntimeException('scheduling_not_found');
        }
        $row = [
            'id' => $this->nextId('ov'),
            'restriction_id' => $restrictionId,
            'reason_code' => $this->requireString($cmd, 'reason_code'),
            'expires_at_utc' => $this->instant($cmd, 'expires_at_utc'),
        ];
        $this->overrides[] = $row;
        $this->emit('scheduling.override_applied', $row['id']);

        return $row;
    }

    /**
     * Metadata only. Never changes eligibility or capacity.
     *
     * @param array<string, mixed> $cmd
     * @return array<string, mixed>
     */
    public function noteInsurance(array $cmd): array
    {
        $start = $this->instant($cmd, 'starts_at_utc');
        $end = $this->instant($cmd, 'ends_at_utc');
        $before = $this->remaining($start, $end);
        $note = [
            'id' => $this->nextId('ins'),
            'subject_ref' => $this->requireString($cmd, 'subject_ref'),
            'provider_label' => $this->requireString($cmd, 'provider_label'),
            'affects_eligibility' => false,
        ];
        $this->insurance[] = $note;
        if ($this->remaining($start, $end) !== $before || $note['affects_eligibility'] !== false) {
            throw new RuntimeException('scheduling_insurance_changed_eligibility');
        }

        return $note;
    }

    /**
     * @param array<string, mixed> $cmd
     */
    private function rejectOperationsCalendar(array $cmd): void
    {
        $kind = $cmd['calendar_kind'] ?? null;
        if (is_string($kind) && in_array($kind, self::OPERATIONS_CALENDAR_KINDS, true)) {
            throw new RuntimeException('scheduling_wrong_calendar');
        }
    }

    /**
     * @param array<string, mixed> $cmd
     */
    private function assertNotRestricted(string $subject, string $at): void
    {
        foreach ($this->restrictions as $restriction) {
            if ($restriction['status'] !== 'active' || $restriction['subject_ref'] !== $subject) {
                continue;
            }
            if ($restriction['tenant_id'] !== $this->tenantId) {
                continue;
            }
            if ($restriction['branch_id'] !== null && $restriction['branch_id'] !== $this->branchId) {
                continue;
            }
            if (is_string($restriction['expires_at_utc']) && $restriction['expires_at_utc'] <= $at) {
                continue;
            }
            if ($this->overrideCovers($restriction['id'], $at)) {
                continue;
            }
            throw new RuntimeException('scheduling_restriction_active');
        }
    }

    private function overrideCovers(string $restrictionId, string $at): bool
    {
        foreach ($this->overrides as $override) {
            if ($override['restriction_id'] === $restrictionId && $override['expires_at_utc'] > $at) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $cmd
     * @param list<string> $from
     * @return array<string, mixed>
     */
    private function requireTransition(array $cmd, array $from, string $to): array
    {
        $this->assertVersion($cmd);
        $row = $this->booking($this->requireString($cmd, 'booking_id'));
        if (! in_array($row['status'], $from, true)) {
            throw new RuntimeException('scheduling_illegal_transition');
        }
        $row['status'] = $to;
        $this->bookings[$row['id']] = $row;
        $this->version++;

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function booking(string $id): array
    {
        if (! isset($this->bookings[$id])) {
            throw new RuntimeException('scheduling_not_found');
        }

        return $this->bookings[$id];
    }

    /**
     * @param array<string, mixed> $cmd
     */
    private function assertVersion(array $cmd): void
    {
        if (! array_key_exists('observed_version', $cmd) || $cmd['observed_version'] !== $this->version) {
            throw new RuntimeException('scheduling_stale_version');
        }
    }

    private function consumingCount(string $start, string $end, ?string $exceptId): int
    {
        $count = 0;
        foreach ($this->bookings as $row) {
            if ($exceptId !== null && $row['id'] === $exceptId) {
                continue;
            }
            if (! in_array($row['status'], self::CONSUMING, true)) {
                continue;
            }
            if ($row['starts_at_utc'] < $end && $start < $row['ends_at_utc']) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $cmd
     */
    private function payment(array $cmd): string
    {
        $payment = $cmd['payment_indicator'] ?? 'none';
        if (! is_string($payment) || ! in_array($payment, self::PAYMENT_INDICATORS, true)) {
            throw new InvalidArgumentException('scheduling_policy_required');
        }

        return $payment;
    }

    /**
     * @param array<string, mixed> $cmd
     */
    private function bodyHash(array $cmd): string
    {
        $payload = [
            'starts_at_utc' => $cmd['starts_at_utc'] ?? null,
            'ends_at_utc' => $cmd['ends_at_utc'] ?? null,
            'subject_ref' => $cmd['subject_ref'] ?? null,
            'payment_indicator' => $cmd['payment_indicator'] ?? 'none',
            'booking_id' => $cmd['booking_id'] ?? null,
        ];

        return hash('sha256', (string) json_encode($payload));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function publicBooking(array $row): array
    {
        return $row;
    }

    private function emit(string $name, string $id): void
    {
        $this->events[] = [
            'name' => $name,
            'subject_id' => $id,
            'sms' => false,
            'channel' => null,
        ];
    }

    private function nextId(string $prefix): string
    {
        $this->seq++;

        return $prefix.'-'.$this->seq;
    }

    /**
     * @param array<string, mixed> $cmd
     */
    private function requireString(array $cmd, string $key): string
    {
        $value = $cmd[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException('scheduling_policy_required');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $cmd
     */
    private function instant(array $cmd, string $key): string
    {
        return $this->mustInstant($this->requireString($cmd, $key));
    }

    private function mustInstant(string $value): string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value)) {
            throw new InvalidArgumentException('scheduling_invalid_interval');
        }

        return $value;
    }

    private function assertInterval(string $start, string $end): void
    {
        $this->mustInstant($start);
        $this->mustInstant($end);
        if ($end <= $start) {
            throw new InvalidArgumentException('scheduling_invalid_interval');
        }
    }
}
