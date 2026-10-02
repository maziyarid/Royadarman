<?php

namespace Tests\Unit;

use App\Domain\Coordination\FollowUpEscalationContract;
use PHPUnit\Framework\TestCase;

final class FollowUpEscalationContractTest extends TestCase
{
    public function test_contract_status_is_proposed_and_priorities_are_not_schema_backed(): void
    {
        $this->assertSame('proposed_not_activated', FollowUpEscalationContract::STATUS);
        $this->assertSame(['routine', 'priority', 'urgent'], FollowUpEscalationContract::proposedPriorities());
        $this->assertFalse(FollowUpEscalationContract::priorityIsSchemaBacked());
        $this->assertSame(['sms', 'email', 'push'], FollowUpEscalationContract::reminderChannels());
    }

    public function test_due_boundary_is_inclusive_and_future_due_dates_do_not_escalate(): void
    {
        $due = FollowUpEscalationContract::evaluateFollowUp(
            'follow_up',
            'open',
            '2026-10-01 12:00:00',
            null,
            '2026-10-01 12:00:00',
        );
        $this->assertTrue($due->due);
        $this->assertTrue($due->overdue);
        $this->assertTrue($due->escalate);
        $this->assertSame('supervisor', $due->level);
        $this->assertSame('overdue_escalate_supervisor', $due->reason);

        $notDue = FollowUpEscalationContract::evaluateFollowUp(
            'follow_up',
            'in_progress',
            '2026-10-01 12:00:01',
            null,
            '2026-10-01 12:00:00',
        );
        $this->assertFalse($notDue->due);
        $this->assertFalse($notDue->overdue);
        $this->assertFalse($notDue->escalate);
        $this->assertSame('not_due', $notDue->reason);
    }

    public function test_evaluation_fails_closed_on_unknown_or_incomplete_input(): void
    {
        $reasons = [
            ['nope', 'open', '2026-10-01 12:00:00', null, '2026-10-01 12:00:00', 'unknown_task_type'],
            ['follow_up', 'paused', '2026-10-01 12:00:00', null, '2026-10-01 12:00:00', 'unknown_status'],
            ['follow_up', 'open', '2026-10-01 12:00:00', null, 'not-a-timestamp', 'invalid_timestamp'],
            ['follow_up', 'done', '2026-10-01 12:00:00', null, '2026-10-01 12:00:00', 'completed_without_timestamp'],
            ['follow_up', 'open', null, null, '2026-10-01 12:00:00', 'due_timestamp_missing'],
            ['follow_up', 'open', 'garbage', null, '2026-10-01 12:00:00', 'invalid_timestamp'],
        ];

        foreach ($reasons as [$taskType, $status, $dueAt, $completedAt, $now, $expectedReason]) {
            $result = FollowUpEscalationContract::evaluateFollowUp($taskType, $status, $dueAt, $completedAt, $now);
            $this->assertFalse($result->due, $expectedReason);
            $this->assertFalse($result->escalate, $expectedReason);
            $this->assertSame('none', $result->level, $expectedReason);
            $this->assertSame($expectedReason, $result->reason);
        }
    }

    public function test_done_tasks_never_escalate_once_a_completion_timestamp_exists(): void
    {
        $done = FollowUpEscalationContract::evaluateFollowUp(
            'follow_up',
            'done',
            '2026-10-01 12:00:00',
            '2026-10-01 11:00:00',
            '2026-10-01 12:00:00',
        );
        $this->assertFalse($done->due);
        $this->assertFalse($done->escalate);
        $this->assertSame('completed', $done->reason);
    }

    public function test_patient_may_be_contacted_once_per_due_cycle(): void
    {
        $allowed = FollowUpEscalationContract::reminderMayContactPatient(0, 'sms', true);
        $this->assertTrue($allowed->allowed);
        $this->assertSame('reminder_permitted_once_per_due_cycle', $allowed->reason);

        foreach (['sms', 'email', 'push'] as $channel) {
            $blocked = FollowUpEscalationContract::reminderMayContactPatient(1, $channel, true);
            $this->assertFalse($blocked->allowed);
            $this->assertSame('patient_already_contacted_this_due_cycle', $blocked->reason);
        }

        $inactive = FollowUpEscalationContract::reminderMayContactPatient(0, 'sms', false);
        $this->assertFalse($inactive->allowed);
        $this->assertSame('case_not_active', $inactive->reason);

        $unknownChannel = FollowUpEscalationContract::reminderMayContactPatient(0, 'letter', true);
        $this->assertFalse($unknownChannel->allowed);
        $this->assertSame('unknown_channel', $unknownChannel->reason);

        $invalidCount = FollowUpEscalationContract::reminderMayContactPatient(-1, 'sms', true);
        $this->assertFalse($invalidCount->allowed);
        $this->assertSame('invalid_contact_count', $invalidCount->reason);
    }

    public function test_reassignment_keeps_attribution_and_escalates_when_overdue(): void
    {
        $preserved = FollowUpEscalationContract::reassignmentKeepsAttribution('user-1', 'user-2', true, false);
        $this->assertTrue($preserved->allowed);
        $this->assertSame('attribution_preserved', $preserved->reason);

        $overdue = FollowUpEscalationContract::reassignmentKeepsAttribution('user-1', 'user-2', true, true);
        $this->assertTrue($overdue->allowed);
        $this->assertSame('attribution_preserved_overdue_escalate_supervisor', $overdue->reason);

        $cases = [
            [null, 'user-2', true, false, 'reassignment_source_missing'],
            ['user-1', null, true, false, 'reassignment_target_missing'],
            ['user-1', 'user-1', true, false, 'reassignment_target_unchanged'],
            ['user-1', 'user-2', false, false, 'reassignment_reason_required'],
        ];

        foreach ($cases as [$previous, $next, $reasonProvided, $overdueAtReassignment, $expectedReason]) {
            $denied = FollowUpEscalationContract::reassignmentKeepsAttribution(
                $previous,
                $next,
                $reasonProvided,
                $overdueAtReassignment,
            );
            $this->assertFalse($denied->allowed, $expectedReason);
            $this->assertSame($expectedReason, $denied->reason);
        }
    }

    public function test_clinic_follow_up_logistics_always_deny(): void
    {
        $cases = [
            [true, '2026-10-02 12:00:00', true, '2026-10-01 12:00:00', 'grant_revoked'],
            [false, 'garbage', true, '2026-10-01 12:00:00', 'invalid_expiry'],
            [false, '2026-10-01 12:00:00', true, '2026-10-01 12:00:00', 'grant_expired'],
            [false, '2026-10-02 12:00:00', true, 'garbage', 'invalid_timestamp'],
            [false, '2026-10-02 12:00:00', false, '2026-10-01 12:00:00', 'membership_inactive'],
            [false, '2026-10-02 12:00:00', true, '2026-10-01 12:00:00', 'followup_logistics_not_wired'],
            [false, null, true, '2026-10-01 12:00:00', 'followup_logistics_not_wired'],
        ];

        foreach ($cases as [$revoked, $expiresAt, $membershipActive, $now, $expectedReason]) {
            $denied = FollowUpEscalationContract::clinicMaySeeFollowUpLogistics($revoked, $expiresAt, $membershipActive, $now);
            $this->assertFalse($denied->allowed, $expectedReason);
            $this->assertSame($expectedReason, $denied->reason);
        }
    }

    public function test_matching_decisions_require_a_rationale(): void
    {
        foreach (['accepted', 'declined', 'reassigned', 'coordinator_override', 'withdrawn'] as $decision) {
            $recorded = FollowUpEscalationContract::matchingRationale($decision, true);
            $this->assertTrue($recorded->allowed);
            $this->assertSame('rationale_recorded', $recorded->reason);

            $required = FollowUpEscalationContract::matchingRationale($decision, false);
            $this->assertFalse($required->allowed);
            $this->assertSame('rationale_required', $required->reason);
        }

        $unknown = FollowUpEscalationContract::matchingRationale('teleported', true);
        $this->assertFalse($unknown->allowed);
        $this->assertSame('unknown_decision', $unknown->reason);
    }
}
