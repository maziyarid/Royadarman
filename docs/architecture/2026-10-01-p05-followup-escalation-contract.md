# P05 follow-up escalation, reminder, reassignment and matching-rationale contract (PROPOSAL)

- Date: 2026-10-01
- Worker: Mistral (backend lane)
- Task: RPH-124 (follow-on slice after M1–M3)
- Status: PROPOSAL — `proposed_not_activated`. No code path calls these classes. No controller, policy, route, job or migration references them.
- References: RPH-52, RPH-64, RPH-6, RPH-91; roadmap `docs/roadmap/2026-09-30-royadarman-roadmap.md` (P05 — Treatment-specialist CRM, follow-up and referral); base lane PR #28 (`vibe/p01-identity-tenancy-f264e5`).
- Authorization provenance: Maziyar approved the M1 conflict-map dispositions and authorised opening the next lane PR. P06/P08/P09 remain gated by dependency order (P06 needs P05, P08 needs unbuilt P04, P09 needs P06+P08).

## What this proposal adds

Two pure contract classes under `App\Domain\Coordination` plus a unit test, following the contract precedent of `WorkspaceGrantCatalogue` and `BranchTenantContract`:

- `FollowUpEscalationContract` — static, stateless decision functions for overdue escalation, patient-reminder dedupe, reassignment attribution, clinic-side follow-up logistics visibility, and matching-rationale recording.
- `FollowUpEvaluation` — immutable value object returned by `evaluateFollowUp`.
- `tests/Unit/FollowUpEscalationContractTest.php` — pure PHPUnit coverage of every reason code.

This slice introduces **no new tables**. Nothing here is marked PROPOSAL at the table level because no table is proposed; the contract relies on the existing `coordination_tasks`, `referral_grants` and `referral_lifecycle_events` shapes and changes none of them.

## Evidence table

| Domain fact | Source (verified from commit patches / repository) | Contract use |
| --- | --- | --- |
| `coordination_tasks`: `case_id`, `assignee_user_id`, `task_type`, `status`, `operational_note`, `due_at`; `task_type ∈ {follow_up, support, referral, home_service, document, other}`; `status ∈ {open, in_progress, done}`; no priority column | PR #42 stack (G2 coordination commits) | `evaluateFollowUp` accepts exactly these task types and statuses; `proposedPriorities()` is explicitly not schema-backed (`priorityIsSchemaBacked() === false`) |
| `ReferralLifecycle` service with append-only `referral_lifecycle_events`; `ReferralLifecycleEventType::requiresReason()` is true for Reassigned/CoordinatorOverride | PR #42 stack | `reassignmentKeepsAttribution` mirrors the reason requirement; `matchingRationale` covers accepted/declined/reassigned/coordinator_override/withdrawn |
| `referral_grants`: `proposal_id`, `case_id`, `clinic_id`, `consent_event_id`, `scope`, `granted_at`, `expires_at` (nullable), `revoked_at` (nullable); clinic case access lives in `PatientCasePolicy`'s referral-grant arm | PR #42 stack | `clinicMaySeeFollowUpLogistics` consumes revoked/expired/membership inputs and always denies |
| `App\Domain\Identity\Authorization\MembershipPermission` — final class, `bool $allowed`, `string $reason` | PR #28 (P01) | Reused verbatim as the decision return type; no new permission class |
| Unit-test house style: `namespace Tests\Unit`, `final class … extends TestCase`, snake-case `test_…(): void` | PR #28 `StaffCapabilitiesTest` | Test file follows it |
| Repository PHP files do not use `declare(strict_types=1)` | main `dbcb114` | Matched; no strict_types added |

## Proposed semantics

### 1. Overdue escalation — `evaluateFollowUp`

- Due boundary is inclusive: `due_at <= now` is due and overdue.
- Overdue open/in-progress tasks escalate to `supervisor` (`overdue_escalate_supervisor`).
- Fail-closed reasons: `unknown_task_type`, `unknown_status`, `invalid_timestamp`, `completed_without_timestamp`, `due_timestamp_missing`, `not_due`, `completed`.
- A `done` task never escalates; a done task without a completion timestamp fails closed instead of guessing.

### 2. Patient-reminder dedupe — `reminderMayContactPatient`

- One patient contact per follow-up per due cycle across all channels (`sms`, `email`, `push`) and across reassignments: a transferred assignment must not contact the patient a second time for the same overdue item.
- Deny reasons: `unknown_channel`, `case_not_active`, `invalid_contact_count`, `patient_already_contacted_this_due_cycle`.
- This is a contract, not a messaging system; nothing is sent, stored or scheduled.

### 3. Reassignment attribution — `reassignmentKeepsAttribution`

- Requires a non-empty new assignee, a retained previous assignee, a changed target, and a recorded reason (mirroring `ReferralLifecycleEventType::requiresReason()`).
- Overdue reassignments return `attribution_preserved_overdue_escalate_supervisor`; in-cycle ones return `attribution_preserved`.
- Deny reasons: `reassignment_target_missing`, `reassignment_source_missing`, `reassignment_target_unchanged`, `reassignment_reason_required`.

### 4. Clinic-side follow-up logistics — `clinicMaySeeFollowUpLogistics`

- Always denies, including for a fully valid grant (`followup_logistics_not_wired`). Logistical notes stay separate from clinical reports; case access keeps living only in `PatientCasePolicy`'s referral-grant arm.
- Deny reasons in order: `grant_revoked`, `invalid_timestamp`, `invalid_expiry`, `grant_expired`, `membership_inactive`, `followup_logistics_not_wired`.
- Revoked grants deny before any expiry evaluation; expired grants (`expires_at <= now`) deny.

### 5. Matching rationale — `matchingRationale`

- accepted, declined, reassigned, coordinator_override and withdrawn decisions require a provided reason.
- Deny reasons: `unknown_decision`, `rationale_required`.

## Non-goals

- No migration, no column, no table, no seed, no UI, no Blade, no CSS, no route, no controller, no policy change, no job, no event, no wiring of any kind.
- No new authorization: every clinic-side decision denies; no grant is created, extended or honoured by this code.
- Priorities (`routine`/`priority`/`urgent`) are proposed labels only — `priorityIsSchemaBacked()` returns `false` until a decision says otherwise.
- Supervisor team view stays with RPH-102 (P02); this contract only emits the `supervisor` escalation level.
- No merchant, fee, holiday closure, retention period, clinical finding or health score.
- Not activated: `STATUS = 'proposed_not_activated'`.

## Test status

NOT RUN. The authoring environment has no PHP binary; PHPUnit was not executed for this slice. The test file must be run (`php artisan test` or `phpunit --filter FollowUpEscalationContractTest` from `backend/`) by the host before any activation or merge. A local SQLite pass would still not be MariaDB proof.

## Rollback

Single revert of the commit that adds these files; no data, schema or configuration is touched. Deleting `backend/app/Domain/Coordination/FollowUpEscalationContract.php`, `backend/app/Domain/Coordination/FollowUpEvaluation.php` and `backend/tests/Unit/FollowUpEscalationContractTest.php` restores the previous state exactly, because nothing else references them.

## P05 acceptance mapping (roadmap)

| Roadmap P05 acceptance | Contract coverage |
| --- | --- |
| Case histories remain attributable through reassignment/referral | `reassignmentKeepsAttribution` (source retained, target changed, reason required, overdue escalation flag) |
| Due follow-ups escalate | `evaluateFollowUp` inclusive due boundary, `overdue_escalate_supervisor`, `supervisor` level; duplicate reminders deduped by `reminderMayContactPatient` |
| Revoked clinic grants cease access | `clinicMaySeeFollowUpLogistics` denies revoked before any other check; expired grants deny |
| Matching rationale and overrides are visible | `matchingRationale` requires a reason for accepted/declined/reassigned/coordinator_override/withdrawn |

Activation of any of these decisions is future, separately reviewed work (P05 implementation slice).
