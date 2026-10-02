# Worklog — p05-coordination-20261001 (Mistral, backend lane)

## Identity

- Worker: Mistral (Vibe, backend lane)
- UTC: 2026-10-01T17:38:13Z · Tehran: 2026-10-01T21:08:13+0330
- Agiflow: project `01M2QBQ08VMQXRMH34VBSBG1DD`, task RPH-124 (`01M3W4JYG317PJXSNGYJ99X2RZ`)
- Repository: maziyarid/Royadarman (private)
- Base: `vibe/p01-identity-tenancy-f264e5` (PR #28, draft; head at slice start `a32edd06a2b44e7afe1bb41ba4b5a7099db5ed9b`)
- Lane: P05 — Treatment-specialist CRM, follow-up and referral (roadmap), refs RPH-52, RPH-64, RPH-6, RPH-91

## Authorization provenance

- M1–M3 of RPH-124 were completed and accepted in the previous run (conflict map on PR #28 and issue #14; three pre-existing failures documented expected-vs-actual with no policy change; `docs/architecture/2026-10-01-branch-workspace-erd-proposal.md` added).
- Maziyar (human authority, above the host agent) explicitly approved the M1 dispositions and authorised opening the next lane PR. Dependency analysis against the roadmap showed P05 is the only viable next lane: P06 needs P05 completion, P08 needs unbuilt P04, P09 needs P06+P08. P05/P06/P08/P09 definitions were requested and P05 alone was opened as a bounded contract-only slice.
- Hard stops honoured: PR #28 stays a draft; no merge, no deploy, no VPS; no migration, no UI, no routes, no policy changes; drafts #17–#20/#24 untouched; Grok 2 stack at PR #42 head `68c3f6e` untouched; `JalaliCalendar.php`, `SeedPanelDemo.php`, `UserRole.php` untouched; ADR-0001 stays proposed; RPH-122 stays blocked; no merchant/fee/holiday-closure/retention-period/clinical-finding/health-score scope.

## Slice content

Bounded contract-only slice following the repo's contract precedent (G2.3 `WorkspaceGrantCatalogue` a85de6c, G2.4 `BranchTenantContract` 21f6951), stacked on the P01 branch:

1. `backend/app/Domain/Coordination/FollowUpEscalationContract.php` — static contract, `STATUS = 'proposed_not_activated'`. Decisions: overdue escalation (inclusive due boundary, fail-closed on unknown type/status/invalid timestamps/missing due/completed-without-timestamp), one patient contact per due cycle across channels and reassignments, reassignment attribution with mandatory reason (mirroring `ReferralLifecycleEventType::requiresReason()`), clinic-side follow-up logistics always denied (revoked before expired before membership; valid grant still `followup_logistics_not_wired`), matching rationale required for accepted/declined/reassigned/coordinator_override/withdrawn.
2. `backend/app/Domain/Coordination/FollowUpEvaluation.php` — `final readonly` value object (`due`, `overdue`, `escalate`, `level`, `reason`).
3. `backend/tests/Unit/FollowUpEscalationContractTest.php` — 8 test methods, pure PHPUnit, house style (`Tests\Unit`, snake-case `void` methods, `assertSame`), every contract reason code covered.
4. `docs/architecture/2026-10-01-p05-followup-escalation-contract.md` — proposal doc with evidence table, semantics, non-goals, rollback and P05 acceptance mapping. No tables proposed.
5. This worklog.

Reused `App\Domain\Identity\Authorization\MembershipPermission` (PR #28) as the decision return type. House style matched: no `declare(strict_types=1)` (repo-wide), final classes, non-capturing `catch (Exception)`, positional constructor promotion.

## Verification status — honest labels

- Implemented: yes, on branch `vibe/p05-followup-escalation-20261001`.
- Tested: NOT RUN at publication time. The authoring environment had no PHP binary; PHPUnit could not be executed. Manual syntax and reason-code review only. See the follow-up entry below for the host-executable runner and the double-checked PHP unavailability.
- Published: draft PR #46 against `vibe/p01-identity-tenancy-f264e5`.
- Deployed: no. VPS untouched.

## Read-back evidence (initial publication)

- Branch `vibe/p05-followup-escalation-20261001` created from P01 head `a32edd06` (verified unchanged before branching); single commit `749ef8d3e5aeec924e0dd9ecb6e11e5cf0c67892` with exactly the 5 expected added files; PR #46 read back open/draft with base `vibe/p01-identity-tenancy-f264e5`; pushed content marker-checked against authoring copies (no `declare(strict_types=1)` header; corrected `invalid_timestamp` vs `invalid_expiry` ordering present).
- Slice record on issue #14 (issuecomment-5949913269); follow-on note on PR #28 (issuecomment-5949914244); Agiflow run report on RPH-124 (comment `01M3Y27SRTSNTCNMDN0Q6VZ91J`).

## Rollback

Revert the commits on this branch (see below). Nothing references the new classes, no schema or config is touched.

## Follow-up entry — verification slice and host runner (2026-10-02)

- UTC: 2026-10-02T10:59:52Z · Tehran: 2026-10-02T14:29:52+0330.
- Trigger: Maziyar said "Proceed" after the slice publication. The outstanding blocker (executing the suite) was re-attempted honestly: PHP was still unavailable. In the upgraded native execution environment, `php`/`git`/`composer` are absent and package installation is explicitly forbidden (apt-get refuses with "Package installation is forbidden in this sandbox"), so PHPUnit still cannot be executed here. **Tests remain NOT RUN by the worker.** No test result is claimed.
- Evidence re-verification against the stored base patches: `coordination_tasks` columns `assignee_user_id`/`task_type`/`operational_note`/`due_at` confirmed; `referral_lifecycle_events` append-only (`updating`/`deleting` throw) with encrypted `reason` confirmed; `CoordinationTaskController` personal scope (`assignee_user_id = current_coordinator_id`) confirmed. No correction needed to the contract or its evidence table.
- Added `checks/p05-followup-escalation-20261001/run.php` — a standalone, vendor-free, database-free runner for the host (one command: `php checks/p05-followup-escalation-20261001/run.php` from the repository root), mirroring the G2.1 `checks/membership-permission-map-20260930/run.php` precedent. It asserts every contract reason code and positive path (37 checks), prints `passed=N failed=M`, and exits non-zero on failure. The PHPUnit suite remains canonical; this runner only gives the host a dependency-free verification path.
- The runner itself was NOT executed here (no PHP binary). It received a manual syntax review plus a structural balance check (braces/brackets/parens outside strings and comments) executed with the available Node runtime: all four PHP artifacts pass the structural check. This is not PHP syntax proof; only the host's `php` run is.
- Scope unchanged: no migration, no UI, no route, no policy, no activation; PR #28 and the G2 stack untouched; no merge, no deploy.

## Next exact item

Host review of the P05 contract slice: run `php artisan test --filter FollowUpEscalationContractTest` (or the standalone runner) from the repository root, review PR #46, then decide the P05 implementation slice. P06 (clinic workspaces/appointments) opens only after P05 completion; P08 requires the unbuilt P04; P09 requires P06+P08. Do not activate any contract decision without a separate reviewed slice.
