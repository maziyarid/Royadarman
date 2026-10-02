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
- Tested: NOT RUN. The authoring environment has no PHP binary (apt/npm installs forbidden in this sandbox); PHPUnit could not be executed. Manual syntax and reason-code review only. The host must re-run the suite (`php artisan test` from `backend/`, or `phpunit --filter FollowUpEscalationContractTest`) before any merge or activation. SQLite passes would not be MariaDB proof.
- Published: draft PR against `vibe/p01-identity-tenancy-f264e5`.
- Deployed: no. VPS untouched.

## Read-back evidence

Recorded after push: branch ref, commit SHA, PR number, file blob SHAs (see the run report comment on RPH-124 and the issue #14 checkpoint).

## Rollback

Revert the single commit; delete the three code/test files and the two docs. Nothing references the new classes, no schema or config is touched.

## Next exact item

Host review of the P05 contract slice and re-run of the suite. P06 (clinic workspaces/appointments) opens only after P05 completion; P08 requires the unbuilt P04; P09 requires P06+P08. Do not activate any contract decision without a separate reviewed slice.
