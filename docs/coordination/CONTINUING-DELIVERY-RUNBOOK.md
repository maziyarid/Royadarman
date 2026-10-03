# Roya Darman continuing delivery runbook

Revision: 30 September 2026, after PR #11 and the frontend deployment.
This is a durable work-selection and handover contract. It supplements, rather
than replaces, docs/roadmap/2026-09-30-royadarman-roadmap.md and the 18 open decisions.
Read it at the beginning of every resumed or externally scheduled run. These
instructions do not themselves schedule, wake, authenticate or extend an agent.
No new prompt is needed to select the next task while this contract remains valid.

## Sources, state and access

Repository: maziyarid/Royadarman. Agiflow project: Medical Websites — Operations &
Growth, 01M2QBQ08VMQXRMH34VBSBG1DD. RPH-WU-1 is a WORK UNIT, not a task: use
get_work_unit with project scope. RPH-57 is the evidence/coverage task; RPH-85 is
the decision/risk register. Repo documents and GitHub worker issues are the shared
coordination source when a worker cannot access that Agiflow account. Codex
mirrors accepted GitHub evidence back to Agiflow. A failed connector lookup is
not evidence that a task does not exist or that work is prohibited.

| Item | Latest evidenced state | Important limit |
| --- | --- | --- |
| Main baseline | PR #11 merged, f99210e05c1fef2e186d76e625164cc4ea101d8a | Fetch current main; this is a dated baseline, not a permanent pin |
| Frontend | PR #12, frontend/20260930, ac7bd87224a37c90c6ef87eb50f222eae7bef0b7; 17 presentation files deployed | PR was open at checkpoint; browser and authenticated walkthrough still unverified |
| Grok 1 calendar package | Original isolated package preserved under agent-work/rph98-20260930; subsequently integrated and corrected by Codex | Original 'not deployed' report is historical; do not copy it over current views/assets |
| Grok 2 membership delta | Latest RPH-96 comment reports two new classes and modified NetworkAdminController deployed; 543 pure-PHP assertions | New source absent in fetched repo; latest live inspection blocked. Publication and HTTP/DB verification still required |
| Restore | Original SQL/site backup integrity verified | Full restore not proven; app DB user lacks CREATE DATABASE; do not expand its privileges |
| Infrastructure access | Latest SentinelX inventory parks Roya host; Maziyar server holds one active slot | Do not pretend VPS commands ran; work on GitHub/isolated tests/contracts while inaccessible |
| Perplexity access | User directs GitHub + Context7 only; no VPS connector in shared account | GitHub scope/runtime must be observed, not assumed; no Agiflow login prerequisite |

Read AGENTS.md, backend/AGENTS.md, the source-sync handover, live-domain contract,
full roadmap, your worker issue and relevant PR comments before edits. Inspect
Dr Bastaninejad inventory on RPH-36 when available; it is reference inventory,
not Roya Darman backend proof. Missing screenshots or inaccessible private pages
must be recorded; use the reference registry without claiming a fresh review.

## Worker lanes and file ownership

| Worker | Coordination issue | Primary lane | Immediate next step |
| --- | --- | --- | --- |
| Grok 1 | GitHub #13 | RPH-94 time/calendar, event/notification correlation, RPH-65/66 scheduling contracts and backend | Correct the Jalali seams with independent expected dates and persisted-event tests |
| Grok 2 | GitHub #14 | RPH-58/96 grants, tenant/schema invariants; then RPH-97 audit, clinical and finance foundations | Publish the live membership delta and tests, then verify assignment through real synthetic HTTP/DB integration |
| Perplexity | GitHub #15 | Authentication/session assurance, readiness and integration-failure security; mobile API security contracts | Per-session recent-auth regression and bounded fix on a GitHub branch |
| Codex | RPH-60/98 and role dashboard tasks | Shared web/patient/staff UI, public map/PWA, API-bound screens; source reconciliation and serial integration | Finish visual/authenticated checks and extend role screens only as corresponding real APIs become available |

These are designated assignments, not claims the external agents have accepted
or begun. Each agent acknowledges its issue/task once it actually starts.
Before a slice, post exact paths, base SHA, objective, expected acceptance and
whether a schema/route/provider change is involved. Review ALL active lane
issues/PRs for cross-lane claims. Comments are coordination records, not locks.
One writer per file at a time. Share a versioned interface proposal for a needed
cross-lane edit; the file owner implements it or explicitly hands it over.

Default ownership: Grok 1 JalaliCalendar, OperationsCalendarController and new
scheduling-domain services/tests; Grok 2 membership/tenant/grant models, role
compatibility, policies and domain migrations; Perplexity auth services/login
responses/recent-auth middleware and scoped health/settings services/tests;
Codex shared Blade/CSS/JS/public assets. routes, providers, configuration,
User/UserRole, shared notification/outbox models and dependency locks need an
explicit coordinated patch before either lane edits them. Grok 2 must not take
Perplexity auth files merely because both concern identity. Notifications used
by scheduling have a declared contract; do not overwrite another domain's sends.

## The repeated run loop

1. Re-read current branch, main, worker issue, checkpoint and open PR reviews.
   Revalidate tools; old permission does not prove current connectivity.
2. Select the first ready, unowned slice in YOUR queue below. If it depends on
   an unfinished contract, choose an independent test, schema proposal or next
   ready slice. Record the dependency once; do not repeatedly request the same
   prompt or wait doing nothing. Recheck a blocked item when its prerequisite
   changes, not through repeated blind retries.
3. Claim bounded paths. Work in a uniquely named branch/worktree. Preserve
   others' changes. Never develop by resetting production or another checkout.
4. State the hypothesis and an observable success/failure criterion. Seek a
   counterexample: wrong role/tenant, revoked grant, expired session, duplicate,
   race, outage, interruption or empty data as applicable. Prefer a real failing
   regression test over assertions that merely mirror a new implementation.
5. Implement the smallest complete vertical slice: backend and persisted state,
   authorised projection/action, lifecycle, audit/outbox and real UI/API binding.
   Cross-lane changes are integrated by the designated owner. Independent API
   contracts and visibly labelled synthetic prototypes may proceed in parallel.
6. Run applicable tests in an isolated environment with synthetic data and
   disabled external delivery. Preserve lockfiles; do not install Boost or upgrade
   dependencies to get started. Use production-family MariaDB for tenant/race/
   constraint proofs. SQLite/unit mocks alone do not establish those properties.
   GitHub without a runtime can still deliver code/tests and a draft PR, labelled
   NOT RUN; it cannot fabricate PHPUnit output or execute commands by narration.
7. Publish a commit/PR or sanitised exact patch. Review and address comments;
   independent review, release gates and integration apply before production.
   VPS HTTPS push was unauthenticated. Do not extract/store tokens or assume
   connector credentials grant a shell push. A GitHub-connected publisher can
   publish an exported patch while the server-only worker continues tests.
8. Checkpoint before an hourly limit, context reset or exit. Include changed
   paths, base/head, tests, deployment status, review findings, remaining risks
   and the next exact command/task. Then select the next ready slice automatically
   within the currently running session. Stop only for an actual access/runtime
   boundary or exhausted authorised work; leave a resumable checkpoint.

No infinite background loops, unattended authentication, blanket auto-merges,
physical-device claims or automatic clinical/financial activation. A genuine
policy/credential/signing decision blocks that activation, not unrelated work.
Collect unresolved decisions for Maziyar instead of interrupting each slice.

### Checkpoint template

- Worker / UTC timestamp / Tehran timestamp / issue and Agiflow task if accessible
- Base SHA / branch / head SHA or patch hash / exact paths and ownership
- Requirement and hypothesis; evidence source (verified, reported, proposed)
- Behaviour changed; backend, schema and privacy effects
- Commands and exit results actually observed; tests NOT RUN and reason
- Implemented / tested / published / deployed as separate fields
- Remaining counterexamples, decisions, dependency and owner
- Backup/rollback and deployment hashes if applicable
- Next ready slice plus exact starting file/test; no vague 'continue development'

## Review of the submitted Grok work

Grok 1 correctly limited presentation work and disclosed Jalali/notification gaps.
Its synthetic screenshots do not prove authenticated rendering or persisted
queries. Codex's later release moved JS externally to retain CSP and replaced
range(1, 0), which created two blank cells, with a zero-safe loop. Shared
frontend release is already recorded; do not redeploy the old package or use
its rollback against later changes. RPH-98 remains unaccepted.

Grok 2 reports an additive assignment map preserving reviewer/contact and existing
credential SQL, with no migration. That is narrower than twelve-workspace
permissions. Helpers denying every combination are not enforcement when unused;
they also cannot replace working policies indiscriminately. Current case/document
policies separately check assignment/credential/consent, and must retain their
positive authorised paths. A membership must not itself grant a clinical read.
The 543 assertions need source review and HTTP/DB regression proof. Specific
follow-ups: SeedPanelDemo direct insert (safe synthetic exemption or shared
assignment validation); reviewer credential expiry/revocation and inactivity;
transaction/race semantics; unauthorised/non-owner caller, invalid member role,
duplicate/expired membership, cross-clinic access, demo restrictions and HTTP422.
A login-page 200 and unchanged queue are availability observations only.

Reported RPH-96 hashes to revalidate before import:
- MembershipPermission.php: 6e7a6371d2105dd4f58dbdcf7a8a88df303422714ce575d5656596d75784f535
- MembershipPermissionMap.php: 4a609aed05343c7640c92e315a67a1b42ecf24a0e43ab594b8765349df5b57aa
- NetworkAdminController.php: d52e7bfed91defd62d29706abd1518cf8ab40218107d6f59902aeb4f0bf766f2
Latest evidence comment: 01M3STHBHPC0ATYQ7WNPFFZBEK. Fresh file inspection was
blocked in this review; no full code approval is implied.

## Lane queues: what to do next without a new prompt

### Grok 1

G1.1 Reproduce/correct 1403-12 and 1404-12 seam defects with an independently
justified calendar algorithm/source and expected fixtures, including actual
1403-12-30 versus invalid 1404-12-30, conversion round trips and leap bounds.
Do not change tests to expect a known defect and call that a fix. Do not invent
an undocumented time.ir API. Keep source/licence/year/update requirements visible.

G1.2 Persist synthetic coordination tasks, home services and referrals at each
Tehran midnight/month boundary; prove half-open UTC query windows include every
in-range event once, exclude adjacent events, and preserve assignment isolation,
invalid-month handling and non-demo coordinator-only access. Controller and grid
must use the same corrected calendar; mobile display is not a query correction.

G1.3 Follow committed domain events through outbox/delivery to the right recipient,
calendar entry and authorised deep link. Test schedule edit, reassignment,
revocation, retries, duplicate jobs, failed provider and notification preferences.
An event need not always send SMS: document channel policy and eligible reasons;
verify intended sends, suppressions and correlation. Use provider fakes.

G1.4 Produce RPH-65/66 scheduling schema/API/state contracts with Grok 2: branch,
clinician/chair/resource/service duration, schedule exceptions, slot/hold expiry,
waitlist, manual/online booking, arrival/reschedule/cancel/no-show and payment
indicator. Coordinator calendar remains distinct. Capacity and last-slot races
require DB row locking, idempotency and invariant tests, not UI availability.

G1.5 Implement scheduling backend in reversible slices once tenant schema and
booking/cancellation/merchant policy prerequisites permit. Grok 2 owns shared
schema integration; Codex owns reception/calendar screens. Continue with roster,
staff availability, operational SLA/escalation and scoped utilisation projections.

### Grok 2

G2.1 Reconcile live-only source and test runner into Git. Verify reported hashes,
back up affected current files outside webroot and document rollback that deletes
new files as well as restores originals. Never treat an old tar as a current source
snapshot or restore DB for a PHP-only rollback. If host inaccessible, document
that exact gap and prepare repo contracts/tests until the delta can be read.

G2.2 Add meaningful assignment route/DB/policy regression tests, preserve all
current authorised paths and record any failure rather than widening privileges.
Resolve SeedPanelDemo's write path deliberately without changing demo into real
users or invoking the seed on production. Use individual synthetic identities.

G2.3 Define additive grants for all twelve workspaces, tenant/branch/team/assignment,
expiry, credential signers, consent and revocation. Keep users.role readers until
all migrations/adapters are proven. Include multiple workspaces per person,
clinic organisation versus personal profile, invitation/offboarding and immediate
revocation through UI/API/search/jobs/export/files/cache. Do not grant owner or
superadmin clinical signing by title. Sensitive access policy remains decision-gated.

G2.4 Deliver the branch/tenant relational contracts and expand migrations with
composite ownership constraints, indexes and existing-case/referral linkage.
Use a fresh consistent backup and separately provisioned restore rehearsal target
before schema deployment. Propose RPO/RTO; do not invent accepted targets.

G2.5 Audit/read/export/privileged-grant lifecycle RPH-97; redacted reason/correlation,
access control and retention decision. Then secure OPG scan/private access,
review versions, tooth/surface findings, dentist sign/release/amend, patient
released projection and treatment stages. Clinical taxonomy, signers and legal
retention activation wait on named accountable owners; synthetic tests proceed.

G2.6 Finance foundation with exact integer currency, immutable balanced ledger,
invoice/receipt, idempotent payment attempt/callback/reconciliation/refund,
resumable abandoned payments, instalment and cheque states. Provider simulation
first; real merchant ownership/credentials and agreed finance rules are gates.
Then clinic inventory/batches/expiry/purchases/maintenance and guardian/merge
contracts where ownership permits. Leave device/auth internals to Perplexity.

### Perplexity — GitHub only

PX.1 Inspect all authentication entry points and EnsureRecentAuthentication;
prove or refute cross-session freshness renewal. Implement/test per-session
assurance for browser paths, clearly define API/device assurance semantics with
the mobile contract, and cover expired/inactive/demo/negative and revocation paths.
Do not use another device's users.last_authenticated_at as session proof.

PX.2 Verify configured-only staff MFA enforcement. Map the agreed intended policy
against current OTP/password/passkey/recovery/enrolment paths. Preserve patient
returning password login. Unspecified enrolment/recovery/step-up policy is an
explicit decision; propose/test it, do not deploy an owner lockout or bypass.

PX.3 Verify LaunchReadiness against stopped/stale workers, overdue/retrying outbox
and failed providers; add redacted observable degraded/unknown states and tests.
An agent without VPS cannot observe live queue status; don't fabricate it.

PX.4 Verify IntegrationSettings DB/decryption/missing-value failure behaviour.
Differentiate unset optional values from corrupted configured values, provide
safe diagnostics, avoid silent unintended provider switches and secret leakage.

PX.5 Produce stable mobile authentication/device/push API contract, logout/all-device
revocation and idempotent upload-resume threat/test cases. Then dependency/security
review, redaction and negative API tests on completed modules. Open new bounded
security issues within this lane rather than editing Grok's tenant/clinical/schema
or Codex's UI files. Use GitHub issue #15 and linked PRs as your checkpoint system.

## Complete delivery sequence and acceptance gates

Coordinates below are sequencing, not actual completion percentages or dates.
Full module/failure/reference register remains in the detailed roadmap. Security,
API contracts, design and tests begin with each domain; they are not postponed.

| Sequence | Deliverable and existing tasks | Dependencies and evidence required |
| --- | --- | --- |
| 0–8 | Source/recovery/evidence RPH-49/57; record Grok delta, frontend release, backups/restore | Git source versus deployed SHA distinct, tested rollback/restore; all gaps assigned |
| 8–15 | Identity, tenants, branches, credentials, sessions/grants and audit RPH-58/59/96/97 | Twelve workspace matrix, old roles preserved, two clinics/multiple branches, denied/revoked actions across every surface |
| 15–23 | Dedicated profiles/dashboards RPH-60/98/99–108 | Owner, developer, superadmin, supervisor, receptionist, accountant, support, specialist, clinic manager, dentist, clinical staff, patient/guardian; real authorised widgets |
| 23–29 | Directory/map, verified clinics/contacts, final logo/social widget RPH-51/56/61 | Mashhad/northern cities/Tehran districts from verified data; map/list outage parity; selected clinic survives onboarding |
| 29–37 | Patient first/returning account and request RPH-50/62/63/88/108/110 | Password plus verified onboarding, optional OPG/'no OPG', interrupted upload/request recovery; own records/guardian authority |
| 37–44 | Specialist/support CRM and referral RPH-52/64/91/105 | Staged notes/duration/due dates, SLA/escalation, assignments/handoffs/revoked referral, minimal support data |
| 44–54 | Clinic/reception scheduling RPH-53/65/66/94/103/106 | Correct Jalali/Tehran times, locked capacity/holds, real booking/manual/cancel/reschedule/no-show/arrival; notification correlation |
| 54–60 | Communications, tasks, scoped video RPH-67/68/111 | Correct recipient, safe logs/attachments, opt-in/preferences/retries; video/recording provider and consent activation gates |
| 60–69 | Clinical workbench and released dental status RPH-69/70/89/90/107/108/109 | Quarantine/scan, assignment/consent, dentist sign-off, tooth-level provenance/unknowns, versioned patient release and treatment plan |
| 69–77 | Clinic finance/accountant RPH-55/71/72/73/87/93/104 | Integer IRR/toman labels, invoice/ledger/callback/retry/reconciliation/refund, instalments/cheques and approvals; merchant gates |
| 77–82 | Scoped reports, CMS/SEO/search RPH-74/75 | Metrics reconcile to source records; no sensitive drill-down leak; multilingual factual content/indexing |
| 82–87 | Providers, rule automation, clinic inventory/roster/maintenance RPH-76/77/112 | Scoped retries/idempotency/diagnostics, no leaked settings, agreed procurement/compliance/payroll boundaries |
| 87–93 | Responsive/PWA plus separate Android AND iOS RPH-78/79/80/81 | Shared versioned APIs; real-device authorised journeys, secure sessions/storage/uploads/push, signed builds and updates |
| 93–97 | Cross-module security/resilience/performance RPH-82/83 | Continuous tests plus final release gates: DB races, recovery, rollback, load/accessibility, redaction and incident response |
| 97–100 | Stakeholder acceptance, pilot and launch RPH-84/85 | Individual accounts, persisted end-to-end demonstrations, training/ownership, honest open risk register; Android/iOS delivery evidenced |

Earliest useful reviewable demo is a vertical slice across these coordinates:
patient request with/without OPG → specialist coordination → authorised clinic/
dentist → signed/released report → patient status, with two synthetic clinics
proving isolation. Reception/booking and accountant/payment join as their actual
APIs become ready. Do not wait for every module to show a real working slice;
do not label an empty visual prototype as a completed workflow.

## Android and iOS: explicit parallel development programme

RPH-78 owns mobile architecture/API decision; RPH-79 PWA; RPH-80 Android; RPH-81
iOS. Android and iOS are separate required deliverables; neither a responsive
site nor a PWA satisfies them. No framework, minimum OS, store account, signing
identity, notification provider or distribution channel is currently assumed.
Select stack through an ADR comparing existing code/API reuse, native file/
security needs, available developers/build hosts and maintenance. Avoid choosing
separate backends/auth services. API design and labelled synthetic UI fixtures
can begin before every backend domain is live; bind each screen to the real API
as its vertical slice becomes available. No permanent fake success buttons.

| Milestone | Deliverable | Verification / release gate |
| --- | --- | --- |
| M0 | ADR, role-route/screen catalogue, supported OS/device matrix, architecture, accessibility, distribution plan | Owner acknowledges unresolved app identity/store/signing/cost/device decisions; compare options without purchases |
| M1 | Versioned API contract for login/MFA/refresh or sessions/devices/logout, tenant selection, pagination/errors/idempotency, uploads and deep links | Security review; no shared/global authorisation token; backward-compatible API/version policy |
| M2 | Buildable Android and iOS shells, Persian RTL/theme/navigation, neutral offline states, accessibility | Android build toolchain; iOS-compatible build host/toolchain required. Linux-only source editing is not an iOS build |
| M3 | Patient onboarding/login/profile, clinic selection, request with/no OPG, upload status/resume, appointments, released reports/treatment, messages and payments | Persisted APIs, interruption/retry/expiry/permissions; report only after clinician release; gateway return not proof of payment |
| M4 | Authorised staff workspaces: specialist/support queues; receptionist calendar/arrival; clinician review; clinic manager/supervisor/owner/finance scopes | Device and role/tenant/branch/assignment/credential/consent denial tests. All required roles accounted for, including mobile-access policy where a workflow needs desktop |
| M5 | Camera/gallery, generic push consent/preferences, deep links and payment handoff, secure token storage, logout/revoke/update | No PHI in lockscreen, clipboard, analytics, app logs, public caches or backups; private cached-data lifecycle explicitly tested; expiry/revocation works offline/online |
| M6 | Automated contract/UI/negative tests and actual Android/iPhone/iPad journeys for supported matrix | Emulator/simulator results labelled separately from real devices; screen reader, low bandwidth, large upload, RTL and non-mirrored radiographs |
| M7 | Signed internal Android artefact and signed iOS build, tester distribution, install/upgrade/key-custody/incident runbooks | Accounts/signing/build access and consent of testers verified. Never store private signing keys in repo/chat; no unapproved purchase or public release |
| M8 | Release review, store/distribution submission and public rollout as owner authorises | Current platform/provider rules verified against official docs, privacy declarations match implementation, reproducible release/version and recovery evidence |

If iOS account/Mac/signing/device access is unavailable, keep that milestone
open with the exact owner/action; continue shared contracts/source/tests and
Android work. Do not silently defer or mark iOS completed. Offline clinical
editing, numeric health scores, AI diagnosis/3D/DICOM and automatic emergency
triage remain separately decided capabilities, not invented app features.

## Decisions, unresolved failures and completion

The detailed roadmap's 18 decisions remain in force. Known technical engine was
already inspected; RPO/RTO/scale/key-custody targets are still unset. Maziyar is
the project decision recipient, not a substituted licensed clinician, finance
controller or legal adviser. Nominate named clinical/clinic/finance/privacy
owners through project governance; agents cannot fabricate these appointments.
Every blocked decision gets: recommendation/options, evidence, accountable role,
affected acceptance/activation, safe work that continues and next review trigger.

No missing feature is silently removed. New evidence goes into the requirement
ledger with original task/reference, implementation, test, publication, release,
ownership and remaining risk. For every domain consider retries, idempotency,
concurrent edits, wrong tenant, expired grants, failed providers, storage/scan/
queue problems, abuse, retention/export, session/recovery and offline leakage.
No finite roadmap predicts every future failure: discovered counterexamples
become regression tests and tracked adjustments. Final acceptance requires real
backend persistence/actions plus authorised and denied role journeys, lifecycle/
race/outage proofs, event/notification/ledger reconciliation, audit/privacy,
restore/rollback, design/accessibility/mobile review and operational handover.
A missing real-world activation prerequisite remains visible, not hidden by Done.

## Production integration boundary

User authorised direct-root incremental deployment after backup; no public
staging is required. That does not authorise blind pull/reset, concurrent writes,
new clinical access, arbitrary real provider sends or store publication. Codex
coordinates integration by default. Grok deploys only a specifically recorded,
reviewed slice with an accepted integration claim. Perplexity never deploys.

For any integrator: obtain flock /home/royadarman/apps/.royadarman-deploy.lock,
re-read expected live hashes, reconcile drift (including Grok live-only delta),
back up exact paths/modes outside public_html, confirm reversible migration/
restore requirements, deploy only reviewed paths as application user, verify
behaviour/hashes and record rollback. Re-check all tests invalidated by the patch.
Preserve separate app-public versus served-webroot assets; old workspace.css
must not overwrite current webroot CSS. Production has no tests directory;
install/run test dependencies in isolation, not in the live app.

Do not restart/disconnect unrelated services to create access. Approved management
slot switching may still be unavailable; record the actual failure, continue
unaffected work and request the specific access change through the integrator
when a live step is genuinely required. An inaccessible VPS is not a reason for
Perplexity to ask for a private connector in the shared account.
