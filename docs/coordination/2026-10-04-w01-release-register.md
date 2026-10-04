# W01 release register — 4 October 2026

## Scope and status

**Release: NOT_READY. Owner acceptance: NOT_RECORDED.** This is the original W01's evidence projection, not a new backlog, scheduler or competing deployment. Native Android/iOS builds remain outside this delivery; all other agreed web/PWA requirements remain in scope. No completion percentage or delivery time is inferred.

Source basis: supplied RD-DELIVERY-20261004-v2 `ACCEPTANCE-MATRIX.md`, SHA256 `a42a6d86775330ffa0700833642d01005aefa448a568e805768eddf333cd0a72`; the full repository roadmap `docs/roadmap/2026-09-30-royadarman-roadmap.md`; DrB's recorded RPH36 reference inventory of 29 September; issue61 and relevant current worker handoffs. The seed contains **45 capabilities and 10 journeys**, all retained below. DrB's inventory is static reference evidence, not a fresh authenticated DrB test. The full roadmap remains authoritative for additional requirements.

Agiflow project: `01M2QBQ08VMQXRMH34VBSBG1DD`. Existing evidence hub RPH57; source/recovery RPH49; decisions RPH85. No task acceptance boxes were changed by this register.

## Version and deployment reconciliation

- GitHub/canonical baseline observed: `9a16918997dfa064364fda47c57b18b4f5e40618`, PR59. W01's 08:10:37Z comparison proved 498 bounded paths matched then; it is not current live parity after later releases.
- Original W01 review checkout: PR62 `9d646acdbed56f3bdf4696800d40fb0a6b260552`, plus the hash-pinned reviewed W04 proposal and four subsequently copied network deltas, only in that isolated checkout.
- PR68 source publication: `83a9e362a9cd16825ecb4e917c7734d3c2a38457`; actual W04 recovery repair, original tests/handoff and independent W01 verification. It is a draft integration publication, not a production release.
- Separate delivery-finalise candidate: `86e7785f47acc569b557b59b9276e6768d70e71f`, tree `b3efde22b1507dfd1a3cda6b9c130b1752b7868c`. Its recorded full suite is 855 tests/36,575 assertions; MariaDB72/834; PWA24 handler checks. W01 read those receipts, not reran them in this resumption. The candidate does not itself prove deployment.
- An urgent-brand release changed live presentation and four network source files while the recorded release identity remained at the older main. The finalise dry-run correctly stopped on drift. Original W01 independently observed 21 changed paths among the original 498; zero unreadable. New assets are outside that original comparison set.
- The separately active delivery-finalise session retains the sole production rollout. This W01 session supplies independent review/source/evidence publication and does not edit or deploy that checkout. Preserve the new network files, existing presentation and all worker packages. Do not use an old clean Git head to overwrite newer live source.

Last recorded next rollout is restricted to six reviewed paths: StaffMfaService, DocumentController, OperationsCalendarController, ProcessOutboxEvent, offline.html and sw.js. It requires incorporation of the four verified network deltas, a freshly frozen combined tree, applicable checks, fresh live hashes, private exact backups and the existing deployment lock. This register does not claim that rollout happened.

## Evidence keys and interpretation

| Key | Source and actual evidence | Limits / reviewer |
| --- | --- | --- |
| E0 | Main9a16918 source, route inventory and dated PR49–59 delivery history | Legacy implementation is not acceptance of expanded requirements. A deployment receipt for the next candidate is missing here. |
| E1 | W01 original baseline46tests/434assertions, PHP8.3.35/PHPUnit12.5.34, actual intake/report/assignment/clinic/referral routes and synthetic SQLite state | Independent W01; no physical browser, provider delivery or appointment/finance proof. |
| E2 | PR62 query-array fix: W03 RED6 malformed cases; W0139tests/30,262assertions and Pint2files PASS on exact published head9d646ac | Independent W01 review5405041165. Coordinator calendar only; not clinic booking, official holiday-source or device acceptance. |
| E3 | Exact W04 recovery repair SHA35ce968964bbacc0ce7c2f220a51c10b1df98b6492d90add464ef9eeab6762b0: W01 RED12/57/8fail; GREEN12/96; existing70/296; physical MariaDB12/96 and five multi-process scenarios PASS | Independent W01 review5405052451 and T02 receipt; source published in PR68. Production-browser recovery and unrelated MFA policy remain open. |
| E4 | Frozen finalise86e7785 receipts read: full855/36575; MariaDB72/834; five recovery races; PWA24; deploy helper18; Pint405 | Results belong to that worker/candidate. Browser navigation was policy-blocked. No new W01 rerun or deployment inferred. |
| E5 | PR66 W08 notification safety at65a29934a0352ce2fbd9a78944c76033dba887dc: worker reports29tests/93assertions; finalise includes the safety patch | Independent changed-code integration still belongs to release verification. This is not an in-app feed or external exactly-once delivery. |
| E6 | W01 independently tested four live network deltas: RED6/48/6fail, GREEN6/102 plus existing12/112; fa/ar/en, real routes and synthetic SQLite | Reserved rows hidden, ordinary/null-email staff retained, no deletion or new membership grant. Receiptf5a996b54f6802eac0d4b4dcfe4a1970b003ec987a4d2d3ba115c70eae60831a. Not production-browser or MariaDB-race proof. |
| E7 | W02 local foundation handoff:10tests/102assertions, real module handlers/migration, source remains separately owned and uncommitted at the reviewed checkpoint | Worker-reported isolated SQLite; transaction-scoped consumer authorisation, current-read/revocation and MariaDB tests remain open. No global role activation. |

Unless a row explicitly says otherwise, its **expanded capability acceptance is OPEN**, independent reviewer is PENDING for the complete requirement, browser/device evidence is NOT_VERIFIED, and the next integration/deployment SHA is UNCONFIRMED. Existing E0 routes/entities are observed baseline surfaces, not proof that every requested lifecycle or role is implemented. E1–E7 are evidence for their specific tested behaviours only. A blocked activation does not close an implementation gap.

## Capability-to-evidence matrix

| ID | Required capability | Lane / existing task | Observed action, data and authority | Evidence / remaining gap and next action |
| --- | --- | --- | --- | --- |
| D01 | Role dashboards and actionable daily overview | W02/W07; RPH58/60/98–108 | Existing `/{locale}/panel` and dashboard families use six legacy roles and scoped projections | E0/E1. Twelve distinct operational workspaces are not delivered; bind each real role/task/action to tested grants rather than aliasing owner metrics. |
| D02 | Personal and clinic profiles | W02/W07; RPH58/60/96 | `/{locale}/panel/profile`, credential/session routes; users, practitioners and clinic memberships are distinct | E0. Expanded branch/workspace profiles and invitation/offboarding need E7 hardening and signed-in tests. |
| D03 | Patient search, details, history and documents | W05/W02; RPH62/69/110 | `/{locale}/panel/cases/{case}` and case/document API; case ownership, assignment, consent and credentials constrain access | E1/E4 partial. Complete patient search/history/export, tenant linkage and all negative channels remain open. |
| D04 | Booking restriction / National-ID blacklist adaptation | W03/W02; RPH65/110 | No accepted Roya restriction/appeal/reversal workflow identified in this candidate | Implement explicitly scoped reasons, audited reversal and authorised matching; do not introduce an undisclosed network-wide blacklist. |
| D05 | Intake identity/history/consent/signature | W05/W04; RPH50/62/63 | POST `/api/v1/cases/draft` and `/{case}/submit`; patient_cases, consent_events, assignments and outbox; server intake gate | E1. First/returning/no-OPG consent and idempotent request evidence is partial; full account linkage/history/signature and interrupted-browser journey remain open. |
| D06 | OTP, password, recovery, MFA and sessions | W04/W02; RPH59/82 | Existing auth OTP/password routes, users, otp_challenges, session assurance and recovery inventory | E3. Atomic recovery fix is published, not yet accepted live here. First credential setup, returning-browser flow, reported OTP error and policy-dependent recovery cases remain open. |
| D07 | Jalali calendar and Tehran time | W03; RPH94 | GET `/{locale}/panel/calendar`; shared server month window, UTC query bounds and coordinator scope | E2. Query-array failure fixed in candidate; clinic day/week/month booking, versioned holiday source and actual notification/device equality remain open. |
| D08 | Hours, future days, capacity and service durations | W03; RPH53/65/106 | Coordinator calendar does not persist clinic resources, chairs, service durations or capacity | G01/G04 scheduling implementation depends on hardened W02 keys/guard; prove freed/expanded capacity remains bookable. |
| D09 | Manual cash/free/online appointment | W03/W06; RPH65/72/103 | No accepted persisted manual appointment/finance linkage identified | Implement three explicit modes with real state, own-branch authority and correct finance source; no synthetic confirmation on live. |
| D10 | Patient slot choice, hold and pending-selection queue | W03; RPH65/66 | No complete accepted clinic hold/selection lifecycle | Persist holds/expiry/idempotency and lock shared resources; require last-slot and overlapping-duration MariaDB races. |
| D11 | Reschedule, cancellation, check-in, no-show and waitlist | W03; RPH65/66/103 | Existing coordination events are not these appointment states | Implement audited transitions, capacity release and recipient/date correlation; test duplicates, closure and stale requests. |
| D12 | Invoice, receipts and payment attempts | W06; RPH71/72/104 | Old FinanceIntegerContract is unwired; component packet is not an invoice/ledger route | Persist branch-owned invoices/attempts/receipts with integer amounts and immutable timestamps; integrate real W02 guard, not an always-allow fixture. |
| D13 | Payment link copy/send/reopen | W06/W08; RPH55/72 | No accepted resumable Roya payment-link route identified | Preserve attempt identity and secure re-entry for registered patients; audit resend/copy, validate origin and expiry. |
| D14 | Verified callback and paid/pending reconciliation | W06/W03; RPH72/65 | Proposed evidence value is not a verified provider adapter or persisted settlement | Fake-provider tests must prove amount/currency/merchant/attempt binding, duplicates, late/unknown states and correct booking/reconciliation outcome. |
| D15 | Ledger, partial payments and adjustment/refund approvals | W06; RPH71/93/104 | No accepted immutable balanced ledger/approval workflow identified | Implement postings, allocations and reversals; test repeat/replay and authorised limits. Real collection/refund activation remains separately gated. |
| D16 | Instalments and cheque lifecycle | W06; RPH73/87/104 | No accepted persisted collection lifecycle identified | Received cheque is not cleared money; implement bounce/replacement/partial allocation and authorised accountant views. |
| D17 | Clinical notes / SOAP and templates | W05; RPH70/107 | Existing ReviewRevision narrative is not a complete SOAP/history/template system | Extend clinician-owned versioned notes with explicit support redaction and authorisation; preserve review provenance. |
| D18 | Private OPG/documents and secure previews | W05/W04; RPH69/109 | POST document upload; GET status/content; clinical_documents, scan attempts, private storage and current participant checks | E4 candidate lets eligible participants learn processing status without granting unscanned bytes. Interrupted/resumable upload, scanner/storage failure and full browser access remain open. |
| D19 | Tooth findings, signed report and amendments | W05; RPH70/107/109 | Existing staff review creation/publication routes; review_revisions/publication_events, eligible clinician checks | E1/E4 partial narrative release. Tooth/surface findings, validated taxonomy, reviewed-content binding and full amendment/retraction remain open. |
| D20 | Patient released report/treatment/status portal | W05/W07; RPH62/108 | Own-case projection selects signed, published, current revisions; availability is metadata | E1. Complete released chart/treatment history, date/reviewer/limitations and one eligible notification still required. |
| D21 | Messages, emails, templates and reply history | W08; RPH67/76/105 | Existing support list/show/reply/internal-note/assignee/status routes; support_conversations/messages/events | E0 partial support. All scoped attachments, email/template/auto-reply lifecycle, authorised search and provider outcomes need verification. |
| D22 | Individual/bulk SMS and delivery monitoring | W08; RPH67/76 | Outbox, ProcessOutboxEvent, notification_deliveries and `/{locale}/panel/deliveries` | E5 protects terminal outcomes, current active recipient and template parameters. No live test campaigns, bulk approval or external exactly-once claim. |
| D23 | Persistent in-app feed/read/unread/preferences | W08; RPH67/F07 | Dashboard report availability is not a feed; no accepted persisted own-user notification workflow yet | Implement committed event, current eligible recipient, own feed/read/preferences and dedupe. Module-local view/catalogues delegated; shared integration remains W01. |
| D24 | Tasks, staged follow-up and supervisor escalation | W08; RPH52/64/68/102 | Existing coordination_tasks/task board with owner/assignment scoping | E0. Complete staged summaries, due-cycle escalation, reassignment history and supervisor/team controls; PR46 proposal alone is insufficient. |
| D25 | Clinic referral, acceptance, matching and home services | W08/W02; RPH52/64/91 | Actual referral proposal/decision/reassign/override and home-service routes; assignments, referral_grants and lifecycle events | E1 covers existing correlation/boundaries. Harden multi-branch handoff, revocation, matching rationale and all affected queues. |
| D26 | Source-reconciled analytics and exports | W08/W06/W07; RPH74/104 | Existing `/{locale}/panel/analytics`, count projections and delivery exports | E0 partial. Branch finance exports and complete metric/source/date reconciliation are open; no clinical drill-down through owner aggregates. |
| D27 | Settings, users, integrations and clinic hours | W02/W03/W08; RPH58/65/76 | Existing network/integrations/administrator/profile controls; minimal scoped settings and provider readiness | E6 independently verifies latest network filters/labels. Expanded tenant management and persisted clinic hours are not complete. |
| D28 | Patient appointments/history/reminders | W03/W05/W08; RPH65/66/108 | Patient case history is not an appointment history | Integrate one persisted booking/payment/time source across patient, reception and reminders; do not infer booking from a coordination task. |
| D29 | Call audio/transcription and review | W08/W05; RPH111/76 | No accepted canonical audio upload/review workflow identified | Private bounded upload, retry, case linkage, original/corrected versions and access tests. Approved processor/consent needed before real recordings leave the system. |
| D30 | Public directory/map/services/content/contacts | W07/W08; RPH51/56/61/75 | Public discovery API/pages, server-rendered fallback and clinic data; new approved branding now on live | E0/E4 partial. Verify real map/browser behaviour, verified social/provider data and selected-clinic preservation through login. No fabricated directory rows. |
| D31 | Legacy URLs, directory listings and crawl separation | W01/W04/W07; RPH49/82 | Existing private-route guards and guest redirects | Full bookmark redirect, directory/backup exposure and authenticated crawl-separation evidence is open; robots/noindex is not access control. |
| R01 | Multi-clinic and branch isolation | W02/W04; RPH58/96/97 | Existing clinic memberships/referral grants plus separately owned local W02 organisations/branches/workspace memberships | E1/E7 partial. Current-read transaction envelope, revocation races, route-loader integration and all channels need production-family proof. |
| R02 | Clinical credentials, delegation and guardian/linkage | W02/W05; RPH58/90/110 | Existing verified practitioner, assignment/consent checks; administrator title is not clinical signing | E0/E1 partial. Guardian/delegate authority, expiry and safe merge remain decision/implementation gaps; no phone/name-only merge. |
| R03 | Treatment stages and recovery check-ins | W05/W08; RPH54/70/89 | Existing home-service state is not a full released clinical treatment plan | Implement attributable versioned treatment stages and scoped recovery/escalation; require clinical approval for live instructions. |
| R04 | Teleconsultation | W08/W05; RPH111 | Preview rooms are not functioning authorised consultations | Implement session/participant/provider/fallback and consent workflow; keep recordings separately gated. |
| R05 | Inventory, batches, usage and procurement | W08; RPH112 | InventoryGuardianContract absence checks do not implement stock | Persist branch stock events/batches/expiry/procurement and approvals; prove authorised usage and finance linkage. |
| R06 | Rosters, onboarding, credentials and maintenance | W08/W02; RPH112 | Existing individual accounts/credentials are not complete workforce/maintenance operations | Implement duties/training/expiry/leave/sign-off; obtain approved operational templates without inventing compliance. |
| R07 | Automation rules and provider failures | W08/W01; RPH76/77/83 | Existing outbox, scheduler/queue heartbeat and provider-readiness infrastructure | Dated process/heartbeat evidence is not current provider delivery. Implement rule preview/approval, replay/backoff and explicit degraded state without a second dispatcher. |
| R08 | Security, audit, backup and recovery | W01/W02/W04; RPH49/82/83/97 | Existing audit, consent, session protections; private source backups and dated isolated restore | E3/E4/E6 partial. Reconcile current source identity and data-safe release rollback; future migrations require specific restore/compatibility proof and named recovery objectives. |
| P01 | Responsive fa/ar/en user journeys | W07; RPH60/98 | Approved presentation/brand candidate, shared locale/layout source | E4 source/test receipts are not actual browser/device acceptance. Verify touch/focus/forms/tables/RTL on signed-in routes; no anatomical mirroring. |
| P02 | PWA installation and launch | W07; RPH79 | Manifest/icons/start/scope and service-worker routes exist; PR64/finalise changes prepared | Supported-browser installation/relaunch has not been established by handler tests or a manifest alone. |
| P03 | PWA offline, logout, user-switch and updates | W07/W04; RPH79/82 | Candidate allowlists three pinned public assets, validates responses and uses natural worker lifecycle | E4 24 handler checks; actual installation, back/forward, logout, account switch and update/unsaved-input browser proof remain open. |
| A01 | Advanced AI/health scores/3D/DICOM/interoperability | W05/W01; RPH86/85 | No validated diagnostic score or patient-specific 3D pipeline accepted | Retain original research scope, provenance and named clinical/privacy gates. No invented scores, diagnoses or reconstruction claims. |
| A02 | Insurance/regional/other originally tracked web extensions | W01/domain owner; RPH87/92/85 | Full roadmap retains these extensions; no accepted implementation inferred here | Inspect each extension against its requirements and policy; assign evidence/owner rather than silently deleting it. |
| H01 | Stakeholder demonstration and operational handover | W01/all; RPH84/85 | Owner requests visible canonical live increments, not ZIP installation or preview-only delivery | Provide exact deployed route/role/release/rollback and actual training/operators. Secure individual invitations and full accepted journeys remain open. |

## Twelve workspace accounting

| Requested workspace | Current evidence disposition |
| --- | --- |
| Owner | Legacy owner management/aggregates exist; no unrestricted clinical authority. Expanded organisation controls need validated grants. |
| Developer | Legacy tech_admin diagnostics are not automatic identity/authority equivalence to the requested developer workspace. |
| Superadmin | No accepted complete dedicated management and exceptional sensitive-access workflow. |
| Supervisor | Team workload/escalation and qualified clinical-supervision scopes remain separate unfinished requirements. |
| Receptionist | Dedicated capacity/arrival/booking workspace depends on actual W03 scheduling. |
| Accountant | No complete ledger/collections/export workspace; never alias to global owner metrics. |
| Customer support | Existing support routes do not by themselves prove the new duty-specific workspace and all redacted projections. |
| Treatment specialist | Existing coordinator functions are reusable, but full staged follow-up/clinic handoff remains open. |
| Clinic manager | Existing clinic_rep/referral membership is not a complete branch/staff/operational-finance dashboard. |
| Dentist | Existing assigned verified clinician narrative review is partial; chart/clinical-workbench scope remains open. |
| Clinical staff | Duty-specific drafting/assistant/resource permissions are not granted merely by clinic employment. |
| Patient | Own-case/request/released narrative exists; appointment/finance/complete dental-status and authorised guardian capability remain open. |

Personal identity/profile, selected membership and clinic business profile must remain distinct. Multiple memberships must not be unioned into cross-clinic access. No named operator or signer is invented by this table.

## Cross-module release journeys

| Journey | Required end-to-end outcome | Current disposition |
| --- | --- | --- |
| J01 | First-time no-OPG request submits once and links the correct account; returning registered patient can sign in and continue | E1 partial route/persistence evidence. Credential setup, interrupted and returning-browser proof OPEN. |
| J02 | Interrupted OPG upload through scan, current clinician, reviewed draft/sign/release/amend, correct own version and one eligible notification | E4 narrative/status tests partial. Tooth/amendment/notification/browser completion OPEN. |
| J03 | Manual/patient booking, last-slot concurrency, exact Tehran/Jalali display, reschedule/cancel, freed capacity and correct reminder, including a date two years ahead | E2 coordinator date tests are not this journey. Clinic scheduling OPEN. |
| J04 | Abandoned payment resumes; verified callback creates one receipt/ledger and correct booking or reconciliation; late/wrong/duplicate callbacks stay safe | No accepted persisted finance journey. OPEN. |
| J05 | Accountant has only scoped finance/export; owner gets permitted aggregates; unrelated clinics/branches/roles are denied across all channels | Existing clinic negatives are partial. Branch/accountant/full-channel proof OPEN. |
| J06 | Revoked grant/session is enforced by browser, queued work and private files, while authorised alternatives still work | E1/E3 partial. Complete current-read/revocation integration and browser/job proof OPEN. |
| J07 | Reassigned support/referral work retains history/due action and correct notification; former assignee loses access | Existing assignment/referral tests partial. Full staged/supervisor/recipient journey OPEN. |
| J08 | PWA install/relaunch/update, logout/account switch/history/offline expose only public fallback and never false booking/payment success | E4 handler proof partial; browser/device journey OPEN. |
| J09 | Provider/corrupt-settings/scanner failures produce recoverable states and redacted diagnostics without unsafe fallback or repeated effects | E0/E5 partial. Cross-module outage and recovery proof OPEN. |
| J10 | Current source/schema recover in isolation without erasing post-release data; handover identifies actual operators/gaps/recovery | Dated restore/helper evidence partial. Current drift/source identity and actual release/handover OPEN. |

## Active ownership and next integration dependencies

W01 original session: independent reconciliation and source/evidence publication. Delivery-finalise continuation: only production rollout. W02: tenant/workspace foundation and consumer authorisation. W03: scheduling; extra controller/module-route/interface paths granted in G04. W04: auth/security; PR68 preserves its repair authorship. W05: patient/clinical. W06: finance; component packet is not a ledger. W07: shared presentation/PWA and next granted login-concurrency slice. W08: communications and practice operations; exact module-local notification views/catalogues delegated by W07.

A startup acknowledgement is not a heartbeat. Recorded access blocks are not proof all connectors are disconnected; they also are not permission to replay a denied action through another worker. Read current legitimate handoffs before editing. The existing supervisor does not wake stopped chats. No new worker or schedule is created here.

Immediate integration sequence: preserve the already-live brand/network changes; freeze/reconcile the finalise tree and release identity; complete the six-path bounded release through its sole owner; integrate hardened tenant/branch/membership interface; then real booking/finance/clinical-notification journeys. Ready independent login/usability and module work continues without waiting for a whole phase.

## Decisions that remain real activation gates

Keep all 18 decisions in the roadmap section10 and RPH85. They include named clinical lead/signers; exceptional sensitive administration; record ownership/consent/retention; guardianship/merge; clinical taxonomy; SLA/emergency/24-hour claims; verified clinic/brand/contact rights; merchant/refund/late-payment rules; instalment/cheque/finance limits; holiday-source/update ownership; AI/privacy validation; schematic versus patient-specific 3D; video/recording processor consent; practice-operation templates; native device/signing/distribution choices; measured database/performance/recovery/key-custody objectives; secure stakeholder invitations; and release staffing/priorities.

Recommendation for unresolved gates: keep the affected real-world action disabled with a precise explanation; continue safe isolated persistence, fake-provider tests, scoped UI and review. Do not adopt legal periods, clinical findings, approval limits, fees or named responsible people without their actual decisions. Native distribution is later scope, not an excuse to stop web delivery. A disabled/unwired required feature remains incomplete unless Maziyar explicitly accepts its deferral.

## Evidence and resumption pointers

Issue61 comments: G01 5977948680; G02 5977993052; G03 5978095937; original W01 T02 completion5978135683; finalise/drift5979803773; no-competing-release5979814409; independent network review5979865345; PR68/G04 handoff5979892099. PR68 contains the source repair and `docs/operations/2026-10-04-w01-recovery-verification.md`.

W01's network probe is `.w01-evidence/W01NetworkPresentationReconciliationTest.php`, SHA256 `25fcea91f0a928607c7aac44c12b8c458bd3bb193d2c9ab609a2d008f04fb985`; its result JSON SHA256 `f5a996b54f6802eac0d4b4dcfe4a1970b003ec987a4d2d3ba115c70eae60831a`. Both are private synthetic evidence, not automatically shipped product files.

On resumption: read current issue61 and release identities first. Reuse matching source-bound evidence, invalidate only changed scope and do not import old stacks blindly. Seventeen recorded G2 PR heads are already ancestors of main; source ancestry is not feature acceptance. Each actual release must supply exact source/schema/asset identity, affected role/routes, independent review, test/browser limits, private backup and data-preserving rollback. Only explicit evidence-backed `WEB_PWA_ACCEPTED` closes delivery.
