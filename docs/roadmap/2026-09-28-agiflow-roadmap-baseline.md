# Historical Agiflow roadmap snapshot
Retrieved 2026-09-30 before revision. Superseded as current scope by 2026-09-30-royadarman-roadmap.md; retained for traceability.

# Roya Darman / Smart Teb — complete delivery roadmap v1
Reviewed 28 September 2026. Requested by Maziyar before further implementation. This is the authoritative expansion plan for this project, not a claim that the product is complete. “0–100” labels are lifecycle coverage bands, not measured delivery percentages or time estimates.

## Outcome
A reusable Smart Teb platform (smarteb.ir brand family), with Roya Darman as a configured deployment: public clinic discovery, verified patient onboarding and portal, treatment-specialist coordination, independent clinic dashboards/CRM, appointment operations, clinic-owned clinical records and treatment plans, finance/payment workflows, administration, and responsive web/PWA plus Android and iOS apps. Dr Bastaninejad CRM is the functional and usability benchmark. Preserve existing working features and improve gaps; do not rebuild blindly.

## Sources and precedence
1. Maziyar's latest manager notes and 28 September request, including comprehensive dashboards and mobile apps.
2. Current production evidence plus durable RPH-21 contract; Git/Agiflow history is evidence, not a substitute for runtime checks.
3. Both supplied Gemini documents: royadarman_platform_expansion_strategy_guide(1).md and Royadarman-ChatGPT-Implementation-Prompt(1).md.
4. Exact reference https://app.drbastaninejad.com/Frontend/pages/staff/dashboard.html and read-only deployed Frontend source inventory on 28 September.
Older MVP exclusions do not remove the newly requested clinic finance/clinical/mobile phases. Clinical authority, consent and tenant ownership still apply. No secrets/patient data copied into planning records.

## Current progress: evidence, not guesses
- Agiflow had 56 tasks and no work units at review start. Preserve original task histories/statuses, including completed foundational work.
- RPH-36 records deployed owner OTP/staff security, coordinator task board, assigned cases, scoped search, communications/support filters, operations calendar, aggregate analytics, policy versioning, launch controls, integration settings, privacy-safe delivery monitoring and PWA shell. Historical release: direct-rph36-deliveries-20260922T082619Z. Historical 538 tests/2018 assertions and 39/169 focused checks are NOT fresh certification.
- Fresh remote check: production source is ahead of repository history; intake remains OFF. Git reconciliation is first delivery dependency. Do not deploy older PR9 over production.
- PR9 head d49432c11282a435e9b9a1b1edcd32f68aa456c6 remains draft baseline; main 88dcfee… is older. PR10 is open/draft/unmerged at 84d92d93394994a2fc603290cdbd2d8f930e3e37, based on the PR9 branch. It contains public contact launcher and general secure-guidance fallback, not clinic-selection preservation. Prior syntax/Blade checks do not constitute full E2E or deployment.
- Existing RPH-5 session revocation, RPH-6 referral SLA, RPH-7 Tehran discovery and RPH-17 integration settings are recorded done. Reuse and regression-test.
- RPH-16 embedded map is blocked following dependency concerns; audit current patched choices before activation; accessible list remains required.
- Real coordinator and verified clinician provisioning, retention/referral TTL choices, and publication of reviewed policies in all supported locales remain launch dependencies.
- No defensible overall implementation percentage yet: P00 establishes release-specific module evidence (planned/source-present/tested/deployed/live-verified).

## Reference CRM coverage matrix
Source-visible controls and API bindings were inspected; authenticated reference E2E was NOT performed (browser redirected to normal login). Static feature presence is not proof that every control works. Preserve a parity checklist and close each item through synthetic authenticated testing.
| Reference area | Observed surface | Destination |
|---|---|---|
| Dashboard | Today schedule, urgent actions, future capacity, website requests, recent transactions/messages, booking/intake shortcuts | P02 |
| Patients | Search, insurance filter/status, next/last visits, bulk message, booking restriction management | P04/P06/P07; insurance initially status metadata, claims only after payer scope |
| Patient detail | Timeline, personal/medical history, files/images, billing, internal notes, edit/new appointment | P04/P08/P09 |
| Calendar | Daily booking list; work hours/capacity/duration defaults and date overrides; visit fee; pending slot-selection requests/SMS; manual booking; cash/online/free; registration source/external-system references | P06/P09/P11 |
| Booking restrictions | Identifier/mobile, reason/date/source, masked identity, operations | P06; explicit reviewed policy and authorised override, never assumed medical eligibility |
| Communications | Website/email channels, unread/starred/closed, labels, refresh | P07 |
| Tasks | Task board, title, priority, deadline, create/complete | P05/P07 |
| EMR | SOAP, speciality fields/templates, draft/sign, before-after images, previous notes, AI draft controls | P08; dental adaptation, licensed author, AI gated |
| Billing | Invoices, patient, rial amount/toman display, status/provider filters, cash/card, paid/pending/failed/refund/insurance states | P09; payer/insurance claims integration separately scoped |
| Payment attempts | Search patient/mobile/reference, status/gateway/date filters, details, CSV | P09 |
| Analytics | Conversion funnel, weekly new patients, payment states, intake sources, response time, web analytics sources | P10 |
| Settings | Clinic details, integrations, users/roles/invite/activation, templates, email auto-reply/signature, working hours | P01/P08/P11 |
| Patient portal | Overview/reminders, appointments/history, records, documents, profile edit, notifications/preferences | P04 |
| Intake | OTP, identity/demographics, reason, medical history/medications, consent/signature | P04; dental appropriate and minimised, not a copy of rhinoplasty fields |
| Auth/errors | Staff activation, password reset/login, patient login, session expiry, offline, 403/404 | P01/P02/P12 |
| Mobile | Install/push controls, manifest and service worker | P12 plus separate Android/iOS apps required by this roadmap |
API source references include auth OTP, appointments, patients, dashboard overview, intakes, EMR templates and AI draft, plus staff push subscription. Reuse concepts and validated contracts, not reference credentials, patient records or brand-specific backend URLs. Gateway names visible in reference differ between screens; implement providers based on actual merchant capabilities.

## Gemini decisions
- ACCEPT: progressive wizard, logical RTL CSS, skeleton/error/empty states, tenant branch switch, private storage, queue isolation, consent revisions, audit/outbox, locale-aware validation, integer IRR, query/lookup caching, SEO/knowledge hub.
- ADAPT: dental symptom mapper stores patient-reported location, not diagnosis; medical questions reviewed by clinician.
- ADAPT: 48-hour feedback with short-lived invitation is configurable, consent-aware and separate from document-retention tasks; feedback does not automatically publish ratings.
- ADAPT: appointment preferences are requests; exact slots become confirmed only through clinic/booking rules and any applicable payment state.
- ADAPT: role security follows current OTP-primary contract; optional stronger MFA belongs in security settings. User-requested username/password is an additional planned path with account recovery, not a replacement or a discarded requirement.
- ADAPT: preserve deployed locale URLs (Persian root) and use IANA Asia/Tehran; do not copy stale DST assumptions or blindly rename existing schemas.
- ADAPT: private upload size/count/type proposals (20 MiB, three JPEG/PNG) are defaults to validate with clinical need and security/hosting limits, not immutable product promises.
- ADAPT: structured data must match actual business identity and published facts; no assumed clinic licensure or fabricated reviews.
- SUPERSEDED: old MVP exclusion of payments cannot veto the newly requested clinic-owned finance phase. This does not authorise Roya Darman to become merchant of record by implication.
- GATED: clinical AI drafting, DICOM/external clinical-system integration, insurance claims and advanced accounting integrations require explicit use cases/provider contracts before activation. Track them in discovery, not silently omit them.
- AVOID: sensitive browser/offline drafts, auto-translated signed clinical facts, exposing private documents through unscoped links, replacing the working Laravel application, default mandatory MFA friction at first login.

## Architectural contract
Laravel modular monolith, MariaDB, progressive-enhancement/Blade web, versioned APIs for mobile; retain verified runtime requirements from source. Shared modules: identity, provider directory, intake, consent, documents, coordination, clinical, finance, communications, content, reporting, operations. Tenant/branch scope in queries, jobs, exports, files and callbacks; brand configuration separate from clinical data. Opaque public IDs and explicit grants; owner administrative power does not imply clinical read access. UTC timestamps, IANA timezone conversion, Jalali display, fa/ar/en, integer IRR and explicit toman boundaries. Durable outbox/idempotency; no critical job shared with slow scans. Avoid unnecessary microservices/dependencies.

## Domain/state contracts
- Person/profile separate from case; verified phone and recovery handle duplicate identity safely.
- Request → triage/follow-up → ready → referral offered → clinic accepted/declined → scheduled → attended/no-show/cancelled → treatment plan → treatment/financial follow-up → closed. Allow documented re-entry/reassignment, not arbitrary state jumps.
- Appointment request, availability hold and confirmed booking are different states; exact selected date/time must match database and notifications.
- Clinical draft → signed revision → immutable amendment; author, time, consent and release status attributable.
- Invoice/obligation separate from payment attempt and ledger posting. Instalment promises and uncleared cheques never count as settled cash.
- Every cross-module object carries tenant/branch/case/reference linkage as appropriate; merge, correction, cancellation and revocation audited.

## Delivery order, parallel work and milestones
P00 before deployment. P01 before new scoped workflows. After that, public/patient (P03–P04), coordinator/clinic (P05–P07), and shared design/API work can proceed in parallel with explicit file/module ownership. P08 precedes live treatment records; P09 depends on appointment/clinical contracts. P10–P11 build on emitting modules. P12 API design starts early; native release follows verified APIs. P13 runs continuously, not only at 92%. P14 starts demo feedback early.
M1: reconciled staging plus owner/coordinator/clinic/clinician/finance/patient synthetic accounts and honest gaps.
M2: discovery → OTP/profile → optional OPG → timed specialist follow-up → clinic acceptance → confirmed appointment works end to end.
M3: clinic visit → signed treatment plan → invoice → online/offline/instalment/cheque → reconciliation works.
M4: full role dashboards, communications, analytics, settings and mobile beta.
M5: controlled clinic pilot, published policies, trained staff, stable native releases and rollout.
No invented dates or percent-done claims. Estimate and set target dates after source reconciliation and critical integration access are known.

## Decisions and activation dependencies
Resolve in task records with responsible role, evidence and date: verified partner clinic list/coordinates/hours; final logo and social handles; specialist follow-up SLA/duration rules; clinic staff/clinician roster; permitted clinical sharing/retention/grant TTL; merchant account and gateway/refund policy; visit fees/instalment/cheque handling; approved booking restrictions and insurance scope; app distribution accounts/platform choice; production SMS/email/push keys and external connector capability. Missing live values block activation, not reversible implementation with synthetic fixtures. Never place credentials in Agiflow.

## Verification and review discipline
Each phase has dependencies, scope, acceptance gate, linked tasks and unchecked tests. Each task records hypothesis → evidence → decision → implementation → test → reflection/next adjustment. Separate code complete, staging verified, deployed and live verified. Require role isolation, audit, responsive/RTL, empty/error states and failure recovery for each feature. Include payment replay, booking races, missed follow-up, revoked access, scan outage, provider outage, timezone/date mismatch, malformed uploads, background mobile session expiry and export leakage. Record defects by severity and stop critical paths on proven failures.

## Definition of 100%
All mandatory phase acceptance criteria pass against identified releases; authenticated reference parity matrix closed or explicit owner-accepted deviation recorded; every role has secure access; complete patient-to-clinic-to-finance lifecycle demonstrated; PWA AND signed tested Android/iOS deliverables exist; policies/clinical/merchant responsibilities settled; backups/restore/rollback/monitoring/training/support proven; no unresolved release-blocking security, data-loss or financial correctness issue. Post-launch improvement continues through evidence-led feedback. Completion of this roadmap document is not implementation completion.

## Phase index
P00 (0–5): Evidence, source reconciliation and release recovery
Dependency: Entry phase; blocks deployment of subsequent changes.
Exit: A reproducible commit builds and restores on staging; production differences explained; no patient data or secrets committed; smoke and rollback demonstrated.

P01 (5–13): Smart Teb shared core, tenants, identity and permissions
Dependency: P00 source baseline; existing identity and referral policy code reused.
Exit: Every role has a tested allowed/denied matrix; clinic/branch isolation enforced server-side; role changes revoke stale access; patient can recover access without creating duplicate profiles.

P02 (13–20): Dashboard parity, navigation and administrative access
Dependency: P00/P01; build on deployed RPH36 rather than replace it.
Exit: Manager can review owner, coordinator, clinic manager/reception, clinician, finance and patient journeys with synthetic data; no dead controls or unauthorised widgets; authenticated reference walkthrough gap list retained.

P03 (20–27): Public discovery, contact, content and brand experience
Dependency: P00/P01; verified clinic geography and contact assets required for activation.
Exit: Map and list produce the same eligible clinics; selection survives login; unknown coverage stated clearly; contact widget does not obscure forms; only verified social links shown.

P04 (27–35): Patient onboarding, consent and self-service portal
Dependency: P01/P03; P08 document security before uploads go live.
Exit: New and returning patients complete request with and without OPG, retain selected clinic and consent provenance, see accurate status and cannot read another patient's case.

P05 (35–43): Treatment-specialist CRM and referral coordination
Dependency: P01/P04; clinic handoff coordinated with P06.
Exit: A case proceeds stepwise through follow-ups to referral with attributable notes and due dates; duplicate jobs do not duplicate contact; missed follow-up visible; revoked grants stop access.

P06 (43–52): Clinic workspace, appointment calendar and reception
Dependency: P01/P04/P05; payment-confirmed booking additionally requires P09.
Exit: Concurrent bookings cannot exceed capacity; patient/UI/database/SMS agree on date; rescheduling and cancellation notify once; exact clinic timezone shown; cross-branch operations denied.

P07 (52–58): Communications, tasks and service desk
Dependency: P01/P05; existing RPH36 queues/support delivery views reused.
Exit: Replies and bulk actions target only authorised recipients, include audit and idempotency, respect preferences and expose delivery failures without secrets or sensitive message leakage.

P08 (58–66): Secure records, OPG, clinical notes and treatment plans
Dependency: P01/P04; clinical ownership, retention and authorised authors defined before live records.
Exit: Only authorised clinicians sign; amendment preserves original; patient sees only released records; malicious or unscanned uploads inaccessible; clinic grants/retention apply to every file and export.

P09 (66–75): Clinic billing, payments, instalments, cheques and accounting
Dependency: P01/P06/P08; separate clinic merchant ownership and finance policy gate real transactions.
Exit: Verified callbacks and duplicate/out-of-order events produce one correct ledger effect; unpaid link is never treated as paid; outstanding equals ledger; refunds/cheques audit correctly; tenant exports isolated.

P10 (75–81): Analytics, reporting, CMS and search visibility
Dependency: P01 and relevant emitting modules; CMS/policy tools already exist in RPH36.
Exit: Sample metrics reconcile to source records and timezone boundaries; clinical/finance data not exposed to owner analytics without rights; no invented ratings, clinics or duplicated locale URLs.

P11 (81–86): Integrations, automation and operational settings
Dependency: P01/P07; provider credentials supplied through protected settings.
Exit: Missing provider yields actionable state; failed provider does not corrupt workflow; retries bounded; secret never returned to client/log; feedback cannot reveal a patient's record.

P12 (86–92): Mobile programme: responsive web, PWA, Android and iOS
Dependency: P01/P04/P06 APIs; P13 mobile security/release checks; API work may proceed alongside web.
Exit: PWA is not counted as native completion. Real-device Android and iOS role journeys pass, revoked devices lose access, health data excluded from cache/backups/logs, and signed distributable builds and update runbooks exist.

P13 (92–97): Security, quality, performance and resilience
Dependency: Continuous from P00; release-blocking verification for every preceding phase.
Exit: No unresolved critical security/data-loss defect; restore/replay/rollback demonstrated; core E2E and role isolation pass; measured performance budgets and alert owners documented.

P14 (97–100): Stakeholder demo, controlled pilot, launch and improvement
Dependency: Demo after P00–P06; clinical/finance/mobile showcases follow their gates. Public rollout requires P13 plus real staffing/policies.
Exit: Owner accepts documented role journeys; pilot data/ledger/referrals reconcile; users have secure individual access; operating owners trained; native releases tested; unresolved items have explicit accepted disposition and recovery plan.


## Additional research review — 28 September 2026
Third attachment reviewed in full: Royadarman Platform Research Strategy.md. Useful proposals are integrated as RPH-88–RPH-93 under existing phases; this does not restart or pause the roadmap.

### New work and placement
- RPH-88 / P04: optional guided dental photo capture, quality hints, original orientation, accessible alternatives and understandable processing/review status.
- RPH-89 / P08: clinician-led recovery check-ins with configurable timing, symptoms/photos/escalation, signed instructions and medication-record viewer.
- RPH-90 / P08: clinician-owned triage protocol, staffed response expectations and peer-review sampling/quality workflow.
- RPH-91 / P05: explainable specialty/capacity/location/budget matching, patient choice, home-dentistry boundary/dispatch eligibility and structured clinic outcome feedback.
- RPH-92 / P01: configurable regional expansion with explicit tenant, membership, consent, hosting and cross-region boundaries.
- RPH-93 / P09: dated clinic-approved cost ranges and future currency contract; preserve exact IRR/toman accounting.

### Adopt or correct
1. Dental/photo/OPG images, tooth numbering and anatomical annotations must NOT be flipped when layout changes to RTL. Navigation can mirror. Phone/OTP and mixed-direction text need explicit isolation. Include visual orientation assertions in web/PWA/Android/iOS tests.
2. 48px primary touch controls and adequate spacing are useful design targets, not a universal WCAG requirement. Use current WCAG criteria and test zoom, screen readers, keyboard, focus, contrast and touch. Do not force a sticky bottom action over the keyboard or contact widget.
3. Geographical service-area scopes support routing, not security isolation alone. Explicit tenant/branch membership, assignment and active consent grants remain necessary even within the same city.
4. Four-photo capture, 48-hour recovery review and automated severity scores are research hypotheses. Make capture optional; clinical lead defines positions, timing, questions and escalation. Do not block referral when patient cannot supply photos/OPG.
5. No automatic antibiotic or medication recommendation engine. Signed clinician instructions/medication records may be displayed with verified translation; authority and prescribing/integration requirements need separate review before activation.
6. Consent revocation stops future grant-based sharing. Do not promise it can undo prior disclosures or automatically erase a clinic's independently retained treatment record; retention and lawful record-controller obligations require explicit policy.
7. International city examples (Dubai/Istanbul and domestic Isfahan/Shiraz) are opportunities, not current partner evidence. Immediate rollout remains verified Tehran districts, Mashhad and named northern cities.
8. Do not deploy the report's example phone, street address, 24/7 opening hours, social handles or schema as real business facts. Select schema type from actual entity role and validate properties; valid schema alone does not guarantee search rich results.
9. Preserve current Persian root URLs. Invalid locale/region paths require deliberate 404/redirect policy, not blanket redirects that could leak sensitive query strings or misroute private links. Auth checks—not robots/noindex—protect private routes.
10. Use cache-control appropriate to actual route/session/content; never apply report-wide public caching to personalised pages. Optimise verified public lookups, not private records.
11. Patient budget is an optional preference, not an automatic clinical suitability decision. Matching should disclose rationale, allow choice/manual review and use real clinic capacity.
12. Report claims such as reducing complications or eliminating duplicate imaging are hypotheses until measured. The architecture description and numbered citations are not proof of implementation. Clinical and regional regulatory assertions need jurisdiction-specific review.
13. Add queue/review latency analytics to P10; sampling quality to P08; privacy-safe worker/outbox monitoring to P13. Existing tasks already cover these foundations; avoid duplicate systems.

### Primary-source cross-check (planning, not local legal/clinical approval)
- HHS types of teledentistry services: https://telehealth.hhs.gov/providers/best-practice-guides/telehealth-oral-health/types-teledentistry-services (reviewed 2026-09-28). Supports considering remote follow-up and guided examination, with protocols and in-person referral for limitations; does not establish a universal four-photo or 48-hour rule, or Iran-specific authority.
- W3C WCAG 2.2 target size explanation: https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html (reviewed 2026-09-28). AA target minimum is 24x24 CSS pixels with specified exceptions; larger product targets can be adopted without mislabelling them as the standard.
- Schema.org MedicalOrganization: https://schema.org/MedicalOrganization (reviewed 2026-09-28). Vocabulary reference only; factual entity representation and valid properties must be checked per published page.

### Traceability and plan state
Master RPH-WU-1 → 15 child phases RPH-WU-2 through RPH-WU-16. New tasks RPH-57–RPH-93 (37 total) complement linked existing tasks. New tasks remain Planning with unchecked acceptance tests. Existing Done/Review/Blocked/In Progress states are retained. Roadmap dependencies are documented in prose, not native dependency edges. No claimed overall completion percentage; release evidence register is RPH-57.

