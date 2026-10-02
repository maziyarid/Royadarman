<?php

/**
 * Standalone P05 contract check (no vendor, no Laravel, no database).
 *
 * Run on the host with the repository checked out at the
 * vibe/p05-followup-escalation-20261001 branch (or after merging PR #46):
 *
 *   php checks/p05-followup-escalation-20261001/run.php
 *
 * Verifies the proposed, not-activated FollowUpEscalationContract decision
 * table: every reason code and every positive path. This mirrors
 * backend/tests/Unit/FollowUpEscalationContractTest.php; the PHPUnit suite
 * remains canonical. The contract is not wired into any controller, policy,
 * route, job or migration, and this script changes nothing — it only reads.
 */

use App\Domain\Coordination\FollowUpEscalationContract;

require __DIR__ . '/../../backend/app/Domain/Identity/Authorization/MembershipPermission.php';
require __DIR__ . '/../../backend/app/Domain/Coordination/FollowUpEvaluation.php';
require __DIR__ . '/../../backend/app/Domain/Coordination/FollowUpEscalationContract.php';

$passed = 0;
$failed = [];

function expect(string $label, bool $ok): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        return;
    }

    $failed[] = $label;
    echo 'FAIL: ' . $label . PHP_EOL;
}

echo 'P05 follow-up escalation contract check (proposed_not_activated)' . PHP_EOL;

// --- Constants and proposed labels ---

expect('status is proposed_not_activated', FollowUpEscalationContract::STATUS === 'proposed_not_activated');
expect('proposed priorities are routine/priority/urgent', FollowUpEscalationContract::proposedPriorities() === ['routine', 'priority', 'urgent']);
expect('priorities are not schema backed', FollowUpEscalationContract::priorityIsSchemaBacked() === false);
expect('reminder channels are sms/email/push', FollowUpEscalationContract::reminderChannels() === ['sms', 'email', 'push']);

// --- Overdue escalation ---

$due = FollowUpEscalationContract::evaluateFollowUp('follow_up', 'open', '2026-10-01 12:00:00', null, '2026-10-01 12:00:00');
expect('due boundary is inclusive (due_at == now is due)', $due->due === true && $due->overdue === true);
expect('overdue escalates to supervisor', $due->escalate === true && $due->level === 'supervisor' && $due->reason === 'overdue_escalate_supervisor');

$notDue = FollowUpEscalationContract::evaluateFollowUp('follow_up', 'in_progress', '2026-10-01 12:00:01', null, '2026-10-01 12:00:00');
expect('future due date is not due and does not escalate', $notDue->due === false && $notDue->escalate === false && $notDue->reason === 'not_due');

expect('unknown task type fails closed', FollowUpEscalationContract::evaluateFollowUp('nope', 'open', '2026-10-01 12:00:00', null, '2026-10-01 12:00:00')->reason === 'unknown_task_type');
expect('unknown status fails closed', FollowUpEscalationContract::evaluateFollowUp('follow_up', 'paused', '2026-10-01 12:00:00', null, '2026-10-01 12:00:00')->reason === 'unknown_status');
expect('invalid now timestamp fails closed', FollowUpEscalationContract::evaluateFollowUp('follow_up', 'open', '2026-10-01 12:00:00', null, 'not-a-timestamp')->reason === 'invalid_timestamp');
expect('missing due timestamp fails closed', FollowUpEscalationContract::evaluateFollowUp('follow_up', 'open', null, null, '2026-10-01 12:00:00')->reason === 'due_timestamp_missing');
expect('unparseable due timestamp fails closed', FollowUpEscalationContract::evaluateFollowUp('follow_up', 'open', 'garbage', null, '2026-10-01 12:00:00')->reason === 'invalid_timestamp');

$doneNoTimestamp = FollowUpEscalationContract::evaluateFollowUp('follow_up', 'done', '2026-10-01 12:00:00', null, '2026-10-01 12:00:00');
expect('done without completion timestamp fails closed', $doneNoTimestamp->escalate === false && $doneNoTimestamp->reason === 'completed_without_timestamp');

$done = FollowUpEscalationContract::evaluateFollowUp('follow_up', 'done', '2026-10-01 12:00:00', '2026-10-01 11:00:00', '2026-10-01 12:00:00');
expect('done with timestamp never escalates', $done->escalate === false && $done->reason === 'completed');

// --- Reminder dedupe (one patient contact per due cycle) ---

$allowed = FollowUpEscalationContract::reminderMayContactPatient(0, 'sms', true);
expect('first contact per due cycle permitted', $allowed->allowed === true && $allowed->reason === 'reminder_permitted_once_per_due_cycle');

foreach (['sms', 'email', 'push'] as $channel) {
    $blocked = FollowUpEscalationContract::reminderMayContactPatient(1, $channel, true);
    expect('second contact denied on channel ' . $channel, $blocked->allowed === false && $blocked->reason === 'patient_already_contacted_this_due_cycle');
}

expect('inactive case denies reminders', FollowUpEscalationContract::reminderMayContactPatient(0, 'sms', false)->reason === 'case_not_active');
expect('unknown channel denies reminders', FollowUpEscalationContract::reminderMayContactPatient(0, 'letter', true)->reason === 'unknown_channel');
expect('negative contact count denies reminders', FollowUpEscalationContract::reminderMayContactPatient(-1, 'sms', true)->reason === 'invalid_contact_count');

// --- Reassignment attribution ---

$preserved = FollowUpEscalationContract::reassignmentKeepsAttribution('user-1', 'user-2', true, false);
expect('reassignment preserves attribution', $preserved->allowed === true && $preserved->reason === 'attribution_preserved');

$overdue = FollowUpEscalationContract::reassignmentKeepsAttribution('user-1', 'user-2', true, true);
expect('overdue reassignment escalates', $overdue->allowed === true && $overdue->reason === 'attribution_preserved_overdue_escalate_supervisor');

expect('missing source fails closed', FollowUpEscalationContract::reassignmentKeepsAttribution(null, 'user-2', true, false)->reason === 'reassignment_source_missing');
expect('missing target fails closed', FollowUpEscalationContract::reassignmentKeepsAttribution('user-1', null, true, false)->reason === 'reassignment_target_missing');
expect('unchanged target fails closed', FollowUpEscalationContract::reassignmentKeepsAttribution('user-1', 'user-1', true, false)->reason === 'reassignment_target_unchanged');
expect('reassignment requires a reason', FollowUpEscalationContract::reassignmentKeepsAttribution('user-1', 'user-2', false, false)->reason === 'reassignment_reason_required');

// --- Clinic-side follow-up logistics (always denied) ---

expect('revoked grant denies', FollowUpEscalationContract::clinicMaySeeFollowUpLogistics(true, '2026-10-02 12:00:00', true, '2026-10-01 12:00:00')->reason === 'grant_revoked');
expect('invalid now timestamp denies', FollowUpEscalationContract::clinicMaySeeFollowUpLogistics(false, '2026-10-02 12:00:00', true, 'garbage')->reason === 'invalid_timestamp');
expect('unparseable expiry denies', FollowUpEscalationContract::clinicMaySeeFollowUpLogistics(false, 'garbage', true, '2026-10-01 12:00:00')->reason === 'invalid_expiry');
expect('expired grant denies', FollowUpEscalationContract::clinicMaySeeFollowUpLogistics(false, '2026-10-01 12:00:00', true, '2026-10-01 12:00:00')->reason === 'grant_expired');
expect('inactive membership denies', FollowUpEscalationContract::clinicMaySeeFollowUpLogistics(false, '2026-10-02 12:00:00', false, '2026-10-01 12:00:00')->reason === 'membership_inactive');
expect('valid grant still denies (not wired)', FollowUpEscalationContract::clinicMaySeeFollowUpLogistics(false, '2026-10-02 12:00:00', true, '2026-10-01 12:00:00')->reason === 'followup_logistics_not_wired');
expect('null expiry still denies (not wired)', FollowUpEscalationContract::clinicMaySeeFollowUpLogistics(false, null, true, '2026-10-01 12:00:00')->reason === 'followup_logistics_not_wired');

// --- Matching rationale ---

foreach (['accepted', 'declined', 'reassigned', 'coordinator_override', 'withdrawn'] as $decision) {
    $recorded = FollowUpEscalationContract::matchingRationale($decision, true);
    expect('rationale recorded for ' . $decision, $recorded->allowed === true && $recorded->reason === 'rationale_recorded');
    expect('rationale required for ' . $decision, FollowUpEscalationContract::matchingRationale($decision, false)->reason === 'rationale_required');
}

expect('unknown decision fails closed', FollowUpEscalationContract::matchingRationale('teleported', true)->reason === 'unknown_decision');

// --- Result ---

echo 'passed=' . $passed . ' failed=' . count($failed) . PHP_EOL;

if ($failed !== []) {
    exit(1);
}

exit(0);
