<?php

namespace App\Domain\Coordination;

use App\Domain\Identity\Authorization\MembershipPermission;
use DateTimeImmutable;
use Exception;

/**
 * Proposed follow-up escalation, reminder, reassignment-continuity and
 * referral-rationale rules for the treatment-specialist coordination lane
 * (roadmap P05; RPH-52, RPH-64, RPH-91).
 *
 * This class is not called by a controller, policy, route or job. It adds no
 * column, no migration and no route. It grants no new authorization: every
 * clinic-side visibility decision denies, and case access keeps living in
 * PatientCasePolicy's referral-grant arm.
 */
final class FollowUpEscalationContract
{
    public const STATUS = 'proposed_not_activated';

    private const TASK_TYPES = ['follow_up', 'support', 'referral', 'home_service', 'document', 'other'];

    private const TASK_STATUSES = ['open', 'in_progress', 'done'];

    private const REMINDER_CHANNELS = ['sms', 'email', 'push'];

    private const RATIONALE_DECISIONS = ['accepted', 'declined', 'reassigned', 'coordinator_override', 'withdrawn'];

    public const ESCALATION_NONE = 'none';

    public const ESCALATION_SUPERVISOR = 'supervisor';

    /**
     * Roadmap P05 names a priority per follow-up. coordination_tasks has no
     * priority column; these are proposed labels, not stored values.
     *
     * @return list<string>
     */
    public static function proposedPriorities(): array
    {
        return ['routine', 'priority', 'urgent'];
    }

    public static function priorityIsSchemaBacked(): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    public static function reminderChannels(): array
    {
        return self::REMINDER_CHANNELS;
    }

    public static function evaluateFollowUp(
        string $taskType,
        string $status,
        ?string $dueAt,
        ?string $completedAt,
        string $now,
    ): FollowUpEvaluation {
        if (! in_array($taskType, self::TASK_TYPES, true)) {
            return self::none('unknown_task_type');
        }

        if (! in_array($status, self::TASK_STATUSES, true)) {
            return self::none('unknown_status');
        }

        $moment = self::parse($now);
        if ($moment === null) {
            return self::none('invalid_timestamp');
        }

        if ($status === 'done') {
            $completed = $completedAt === null || $completedAt === ''
                ? null
                : self::parse($completedAt);

            return self::none($completed === null ? 'completed_without_timestamp' : 'completed');
        }

        if ($dueAt === null || $dueAt === '') {
            return self::none('due_timestamp_missing');
        }

        $due = self::parse($dueAt);
        if ($due === null) {
            return self::none('invalid_timestamp');
        }

        if ($due > $moment) {
            return self::none('not_due');
        }

        return new FollowUpEvaluation(true, true, true, self::ESCALATION_SUPERVISOR, 'overdue_escalate_supervisor');
    }

    /**
     * One patient contact per follow-up per due cycle, regardless of channel
     * and regardless of reassignment. A transferred assignment must not
     * contact the patient a second time for the same overdue item.
     */
    public static function reminderMayContactPatient(
        int $patientContactsThisDueCycle,
        string $channel,
        bool $caseActive,
    ): MembershipPermission {
        if (! in_array($channel, self::REMINDER_CHANNELS, true)) {
            return new MembershipPermission(false, 'unknown_channel');
        }

        if (! $caseActive) {
            return new MembershipPermission(false, 'case_not_active');
        }

        if ($patientContactsThisDueCycle < 0) {
            return new MembershipPermission(false, 'invalid_contact_count');
        }

        if ($patientContactsThisDueCycle > 0) {
            return new MembershipPermission(false, 'patient_already_contacted_this_due_cycle');
        }

        return new MembershipPermission(true, 'reminder_permitted_once_per_due_cycle');
    }

    /**
     * A reassignment keeps case history attributable: the previous assignee
     * is retained, the new assignee differs and a reason is recorded,
     * mirroring ReferralLifecycleEventType::requiresReason().
     */
    public static function reassignmentKeepsAttribution(
        ?string $previousAssigneeId,
        ?string $newAssigneeId,
        bool $reasonProvided,
        bool $overdueAtReassignment,
    ): MembershipPermission {
        if ($newAssigneeId === null || $newAssigneeId === '') {
            return new MembershipPermission(false, 'reassignment_target_missing');
        }

        if ($previousAssigneeId === null || $previousAssigneeId === '') {
            return new MembershipPermission(false, 'reassignment_source_missing');
        }

        if ($previousAssigneeId === $newAssigneeId) {
            return new MembershipPermission(false, 'reassignment_target_unchanged');
        }

        if (! $reasonProvided) {
            return new MembershipPermission(false, 'reassignment_reason_required');
        }

        return new MembershipPermission(
            true,
            $overdueAtReassignment
                ? 'attribution_preserved_overdue_escalate_supervisor'
                : 'attribution_preserved',
        );
    }

    /**
     * Clinic-side follow-up logistics stay grant-gated. This decision always
     * denies: revoked and expired grants must cease access, and a valid
     * grant still authorizes nothing in this slice.
     */
    public static function clinicMaySeeFollowUpLogistics(
        bool $grantRevoked,
        ?string $grantExpiresAt,
        bool $membershipActive,
        string $now,
    ): MembershipPermission {
        if ($grantRevoked) {
            return new MembershipPermission(false, 'grant_revoked');
        }

        $moment = self::parse($now);
        if ($moment === null) {
            return new MembershipPermission(false, 'invalid_timestamp');
        }

        if ($grantExpiresAt !== null && $grantExpiresAt !== '') {
            $expiry = self::parse($grantExpiresAt);
            if ($expiry === null) {
                return new MembershipPermission(false, 'invalid_expiry');
            }

            if ($expiry <= $moment) {
                return new MembershipPermission(false, 'grant_expired');
            }
        }

        if (! $membershipActive) {
            return new MembershipPermission(false, 'membership_inactive');
        }

        return new MembershipPermission(false, 'followup_logistics_not_wired');
    }

    /**
     * Clinic selection, acceptance, decline, override and withdraw decisions
     * carry a visible rationale (roadmap P05 acceptance).
     */
    public static function matchingRationale(
        string $decision,
        bool $reasonProvided,
    ): MembershipPermission {
        if (! in_array($decision, self::RATIONALE_DECISIONS, true)) {
            return new MembershipPermission(false, 'unknown_decision');
        }

        if (! $reasonProvided) {
            return new MembershipPermission(false, 'rationale_required');
        }

        return new MembershipPermission(true, 'rationale_recorded');
    }

    private static function none(string $reason): FollowUpEvaluation
    {
        return new FollowUpEvaluation(false, false, false, self::ESCALATION_NONE, $reason);
    }

    private static function parse(string $timestamp): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($timestamp);
        } catch (Exception) {
            return null;
        }
    }
}
