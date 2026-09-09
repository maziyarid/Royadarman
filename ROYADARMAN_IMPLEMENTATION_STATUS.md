# Royadarman implementation status

Resumable single-source implementation record. Updated as work progresses.
Branch: `feat/royadarman-completion` (based on `main`, the canonical baseline).
Overbuild branch `vibe/backend-comprehensive-5ec088` is preserved as a
salvage/future-reference source only; it is **not** the development base.

Status legend: `NOT_STARTED`, `PARTIAL`, `IMPLEMENTED_UNVERIFIED`, `VERIFIED`,
`BLOCKED_EXTERNAL`, `FAILED`.

## Source-of-truth audit (Phase 0)

| Area | State | Evidence | Missing / Next action |
| --- | --- | --- | --- |
| Git base | VERIFIED | `main` @ `4d6e3ab` is the clean canonical baseline (14 models, 9 controllers, 8 migrations, intake gated). New branch `feat/royadarman-completion` created from `main`. | — |
| Overbuild branch | VERIFIED (audited) | `vibe/backend-comprehensive-5ec088` = single commit `536f012`, the marketplace/payment/matching/scheduling overbuild. CMS, dashboards, SupportTicket, extra tests claimed in prior chat sessions are **not** in Git. | Preserve as future-reference. Do not merge. See salvage matrix below. |
| Root README | VERIFIED (corrected) | Was "Managed dental marketplace … owns payment … settlement"; rewritten to current canonical scope + SUPERSEDED notice pointing to `backend/ARCHITECTURE.md`. | Commit. |
| Old ADRs/docs | VERIFIED (marked) | `docs/01-adrs.md`, `02-architecture.md`, `03-phase-0.md`, `04-iran.md`, `05-data-model.md` carry SUPERSEDED/PARTIALLY SUPERSEDED headers. `docs/00-reuse-verdict.md` (ADR-001) still valid. | Commit. |
| Sandbox PHP/Composer | VERIFIED (dev) | PHP 8.4.24 + Composer 2.8.8 installed in sandbox (dev/test only; production target stays PHP 8.3). `composer install` OK. | Runtime results recorded in the section below. |
| Production MCP | VERIFIED (read-only) | `royadarman-admin` SSH profile -> `mcpadmin@64.21.191.116` (hostname `server.royadarman.com`), passwordless sudo. Read-only drift inspection complete (see Production state below). | Continue read-only; no production writes without explicit approval. |
| ClickUp | NOT_STARTED | Legacy Monday-import + a public-site-unreachable task from an earlier outage. | Reconcile against current live evidence; update existing tasks, do not rewrite all legacy imports. |

## Production state (read-only drift comparison, live)

Server: `64.21.191.116`, hostname `server.royadarman.com`. App path:
`/home/royadarman/apps/royadarman-backend` (matches DEPLOYMENT.md). Public
root: `/home/royadarman/public_html` (Laravel front controller + approved
assets + preserved `mcp/` only). Private uploads:
`/home/royadarman/private_uploads/{quarantine,approved}` (root-owned,
drwxr-x---, not web-reachable).

| Check | Production value | Matches `main`? |
| --- | --- | --- |
| Live `/up` | `{"status":"ok"}` | Yes |
| Live `/fa/`, `/ar/`, `/en/` | All render canonical content (no-diagnosis, Tehran-only, intake-disabled, licensed-dentist-only) | Yes |
| `APP_ENV` / `APP_DEBUG` | production / false | Yes |
| `INTAKE_ENABLED` | **false** (kill switch enforced) | Yes (matches open gates) |
| `DB_CONNECTION` | mysql (MariaDB 10.11.19) | Yes |
| `QUEUE_CONNECTION` / `CACHE_STORE` | database / database | Yes |
| Web/FPM + worker + scheduler PHP | **ea-php83** (PHP 8.3) | Yes (CLI default is 8.2 but runtime uses 8.3) |
| ClamAV | 1.4.6, fresh sigs (2026-09-09) | Installed & current |
| Queue worker | `royadarman-queue.service` active/running/enabled, ea-php83, systemd-supervised | Yes |
| Scheduler | cron `* * * * *` -> `ea-php83 artisan schedule:run` | Yes |
| Failed jobs | none | Clean |
| Deployed models | 14 (same set as `main`) | **Identical** |
| Deployed controllers | 9 (same set as `main`) | **Identical** |
| Deployed Domain dirs | Cases, Documents, Identity, Operations (4 canonical; NO Finance/Matching/Scheduling/CMS) | **Identical** |
| Deployed migrations | 7 Royadarman + 3 base = same set as `main`; all Ran on MariaDB | **Identical** |
| Overbuild on production | **None** | Overbuild never deployed |
| Git in deploy dir | **Not a git repo** (code drop / release snapshot) | n/a |

Drift verdict: **production source == `main` source.** The overbuild branch
was never deployed; the marketplace/payment/matching/scheduling code is not
live and never was. Production is healthy and matches the canonical
architecture. No redeploy needed now; new work on
`feat/royadarman-completion` will need a deliberate deploy later.

## Runtime verification of `main` baseline (PHP 8.4 dev sandbox)

Production target remains PHP 8.3; sandbox runs 8.4 for dev/test only.

| Check | Command | Result |
| --- | --- | --- |
| App boots | `php artisan about` | OK (env local, cache database, queue database, storage public NOT LINKED) |
| Migrations | `php artisan migrate --force` (sqlite) | 8/8 OK (users, cache, jobs, case tables, scan tracking, identity/provider/consent, coordination/clinical/operations) |
| Routes | `php artisan route:list` | 22 routes, all canonical (auth/otp, cases, documents, referrals, staff, policies, me, notifications/callback, up, locale home). **No overbuild routes.** |
| Tests | `php artisan test` | **25 passed / 81 assertions** (matches CHECKLIST baseline) |
| Security | `composer audit --no-dev` | No advisories |
| Formatting | `php vendor/bin/pint --test` | PASS (95 files) |

Notes: `phpunit.xml` gained a stable test `APP_KEY` (tests previously
threw `MissingAppKeyException`). `backend/.gitignore` added (vendor, .env,
sqlite, storage runtime caches). Repo previously had no `.env.example`
(gap to address). View-cache dir `storage/framework/views` must exist for
the locale-render test.

## Current canonical baseline (`main`) — what actually exists

Verified by direct file inspection (not by chat claims).

| Domain | State | Evidence | Missing | Next action |
| --- | --- | --- | --- | --- |
| Identity — OTP auth | IMPLEMENTED_UNVERIFIED | `OtpService`, `OtpChallenge`, `HttpOtpSender`, `AuthController` (challenge/verify/logout), throttle 20/60 & 30/60, `User` casts phone/totp encrypted. | No PHP runtime test yet. | Verify on PHP 8.3. |
| Identity — Staff MFA | PARTIAL | `TotpVerifier` exists; `mfa_recovery_codes` cast on User. | Recovery-code flow, rapid revocation, idle/session-expiry controls not verified. | Complete + test. |
| Identity — Roles | IMPLEMENTED_UNVERIFIED | `UserRole` enum: patient, coordinator, clinician, clinic_rep, owner, tech_admin. `isStaff()`. | Role-grant enforcement in policies not fully covered for every role. | Extend policy tests. |
| Intake — Cases | IMPLEMENTED_UNVERIFIED | `PatientCase`, `CaseWorkflow`, `SubmitPatientCase`, `CaseStatus` enum with explicit `allowedTargets()` state machine, `StorePatientCaseRequest` (Tehran-area validation). | No PHP runtime test. | Verify on MariaDB. |
| Intake — Kill switch | IMPLEMENTED_UNVERIFIED | `EnsurePatientIntakeEnabled` middleware wraps every intake route (draft/submit/documents/referrals/staff actions) in `api.php`. | Direct-while-disabled bypass test exists in `PatientCaseIntakeTest`; needs runtime run. | Verify on PHP 8.3. |
| Consent | IMPLEMENTED_UNVERIFIED | `PolicyVersion`, `ConsentRecord`, `PolicyController`; fail-closed missing-translation design documented. | Runtime verification; legal text is an activation gate. | Verify; Gate A external. |
| Documents — OPG pipeline | VERIFIED (local) | `ClinicalDocument`, `ScanAttempt`, `QuarantineClinicalDocument`, `ClamAvDocumentScanner` (fails closed when disabled/unavailable/no-verdict), `ScanClinicalDocument` job (hash verification on quarantine + promotion, retention scheduling), `DocumentScanner` contract, private storage design. Fixed incorrect 'PDF accepted' message. 4 feature tests incl. fail-closed scanner + renamed-executable rejection. | ClamAV live drill (Gate E external); runtime verify on PHP 8.3 + MariaDB. | Live drill. |
| Coordination — Assignments | IMPLEMENTED_UNVERIFIED | `CaseAssignment`, `coordination_tasks` table, `StaffCaseController` (assign/status/proposeReferral/createReview/publishReview). | Runtime verify. | Verify. |
| Coordination — Referrals | IMPLEMENTED_UNVERIFIED | `ReferralProposal`, `ReferralGrant`, `ReferralController` (patient decision), `referral_grants.scope` JSON for minimum-data sharing. | Runtime verify. | Verify. |
| Clinical review | PARTIAL | `review_revisions` table + `ReviewRevision` model (encrypted fields, signed_at). `publication_events` table exists but **no `Review` parent model**, no `PublicationEvent` model. | Add `Review` + `PublicationEvent` models; verify assigned-licensed-clinician-only publish. | Build models. |
| Operations — Outbox | IMPLEMENTED_UNVERIFIED | `OutboxEvent`, `Outbox` service, `Idempotency`, `ProcessOutboxEvent` job, `DispatchOutbox` command. | Runtime verify; transaction-after-commit semantics. | Verify. |
| Operations — Notifications | IMPLEMENTED_UNVERIFIED | `notification_deliveries` table, `HttpNotificationSender`, `NotificationCallbackController`. | SMS provider abstraction (no hard-coded provider); Gate B external. | Build SMS adapter. |
| Operations — Retention | IMPLEMENTED_UNVERIFIED | `retention_jobs` table, `RunRetention` command. | Retention period is an activation gate (Gate C external). | Verify; Gate C external. |
| Operations — Audit | IMPLEMENTED_UNVERIFIED | `AuditEvent` model, `audit_events` table. | Runtime verify; ensure no OTP/PII/secret storage. | Verify. |
| Provider network — models | VERIFIED (local) | `Clinic`, `Practitioner`, `ClinicMembership`, `PublicationEvent` Eloquent models + `CredentialStatus` enum added on `feat/royadarman-completion`; relationships complete on `ReviewRevision`/`User`/`PatientCase`. `php artisan test` green. | Runtime verify on MariaDB once env up. | Deploy/migrate. |
| Home dentistry | IMPLEMENTED_UNVERIFIED | `ServiceType::HomeDentistry` enum + intake Tehran-area question + **`HomeServiceStatus` state machine** (requested→area_verified→coordinator_review→provider_requested→provider_accepted→patient_confirmed→scheduled→completed, terminals: cancelled/rejected/unable_to_service) on `home_service_requests`/`home_service_status_events` tables; `HomeServiceRequest`/`HomeServiceStatusEvent` models; 4 unit tests. Precise address NOT stored at request (only tehran_area). | Wire controller/routes + feature test; verify on MariaDB. | Build controller. |
| Support system | VERIFIED (local) | `support_conversations`/`support_messages`/`support_status_events` tables; `SupportConversation`/`SupportMessage`/`SupportStatusEvent` models; `ConversationStatus`/`SupportCategory`/`SupportPriority` enums; `SupportController`; `SupportConversationPolicy`; 9 feature tests (create, cross-patient denial, internal-note isolation, patient-cannot-internal-note, status state-machine + event, invalid transition rejected, scoped list). Built fresh per §20. | Runtime verify on MariaDB. | Deploy/migrate. |
| CMS / Marketing | PARTIAL | 16 CMS tables (posts, translations, categories, tags, media, menus, seo_metadata, redirects, comments, revisions), 16 Eloquent models, PostStatus/PostType/CommentStatus/MenuItemType enums, HtmlSanitizer (no stored XSS), CmsPostController (CRUD+publish+unpublish), CmsPostPolicy (owner/tech_admin/coordinator-editor; patient/clinician denied), 8 post feature tests. **Added:** CmsCategoryController (CRUD+show, hierarchical, self-parent prevented), CmsTagController (CRUD+merge, tag-merge moves post_tag pivot; self-merge rejected), CmsRedirectController (CRUD, 409 on duplicate source_path, 301/302 only), CmsMediaController (CRUD, `public-cms` disk separate from OPG, dimensions+sha256, multilingual), CmsMenuController (menu CRUD + nested menu items with translations, self-parent prevented, item scoped to menu), CmsSeoMetadataController (polymorphic per entity+locale upsert, idempotent, locale-isolated), CmsCommentController (index/moderate/destroy, 4 statuses), CmsContentPolicy (owner/tech_admin manage+delete; coordinator manage-only; patient/clinician denied). `public-cms` disk in `config/filesystems.php`. 37 CMS API routes registered (auth, NOT behind intake kill-switch). 22 API feature tests (CmsContentTest + CmsManagementTest). Fixed route-binding bug (`{tag}` binds to `$tag`). **CMS admin UI (§22):** `AdminCmsController` (full CRUD: index/create/store/show/update/publish/unpublish/destroy) with policy-gated access; `EnsureStaffAccess` middleware (403 non-staff, guest redirect to `/fa/`); admin layout + posts index (search/type/status filters, pagination, publish/unpublish/delete actions) + post editor (multilingual fa/ar/en tabs, categories, tags, revisions history); `admin` translation block (38 keys × 3 locales, parity verified); routes `/admin/cms/posts/*` + `/admin` redirect behind `staff` middleware. 5 admin UI tests (AdminCmsUiTest): guest redirect, patient forbidden, owner lists/creates/stores multilingual/publishes. | Categories/tags/media/menus/SEO/redirects/comments admin UI; rich-text editor + HtmlSanitizer wiring in editor; remaining CMS UI per §22. | Continue. |
| Role dashboards | NOT_STARTED | No dashboard controllers/services/views on `main`. Prior dashboard claims were uncommitted. | Build per-role UI per §13, clinical scope (no earnings/settlement/payment). | Build. |
| Public frontend | PARTIAL | `Current Public_HTML/` + `Front-end v1/` static multilingual frontend; `public/home.blade.php` minimal; approved identity in `public/assets/`. | Reconcile static vs Blade; connect to Laravel routes; keep approved design. | Reconcile. |
| Multilingual | VERIFIED (local) | `lang/{fa,ar,en}/ui.php` (60 keys each, full parity verified). `lang/{fa,ar,en}/validation.php` added (full Laravel rule set + Royadarman `attributes`: mobile/service_type/tehran_area/budget_band/consent/policy_version/locale etc.). `SetLocale` middleware (route param > `X-Locale` header > user locale, default `fa`; unknown locale aborts 404). `/fa/`,`/ar/`,`/en/` routes render `<html lang dir>` correctly (RTL fa/ar, LTR en). `__('ui.services.'.$service.'.title')` dynamic-key pattern is controller-bound (safe). Consent **fail-closed verified**: `CaseController::submit` looks up published `PolicyVersion` for `policy_key='case_coordination'`+`version`+`locale` and returns 503 `error.consent.translation_unavailable` when missing — covered by `test_missing_consent_translation_blocks_submission`. Validation localization verified by `test_validation_messages_are_localised_per_locale` (fa="شماره همراه", ar="رقم الهاتف", en="mobile number"). | Legal/consent copy is an activation gate (Gate A external). CMS/dashboards/admin UI translation coverage to audit when those UIs land. | Continue. |
| SEO | VERIFIED (local) | `SitemapController` (sitemap index `sitemap.xml` + per-locale `sitemap-{fa,ar,en}.xml` from published posts/pages/services only; excludes drafts/admin/api/private media). `RobotsController` (`robots.txt` allows `/`, disallows `/api/`,`/admin/`,`/cms/`,`/storage/`, references sitemap). `BlogController` renders published CMS posts at `/{locale}/blog/{slug}` with per-locale canonical, hreflang (fa/ar/en + x-default), robots directive, OpenGraph/Twitter Card, JSON-LD `schema_data` from `cms_seo_metadata`. Home view already had canonical/hreflang/meta-description/robots. 5 feature tests (SeoPublicTest): robots served with sitemap ref, sitemap index lists locale sitemaps, locale sitemap includes home+published-only, blog show renders canonical+hreflang, draft returns 404. | Structured-data schemas (Article/FAQPage/Organization/BreadcrumbList) to populate per §27 when real content lands; redirect manager wiring to public 404 path; CMS media OG image serving route. | Continue. |
| Role dashboards | IMPLEMENTED_UNVERIFIED | `DashboardService` + `DashboardController` (`GET /api/v1/dashboard`, auth). Single dispatcher returns role-specific clinical/coordination payloads (NOT marketplace): **patient** (own cases/documents-count/has_published_review, open referral proposals, home-service requests, support conversations), **clinician** (credential_status + can_publish gate, assigned current review revisions via `whereDoesntHave('supersededBy')`, open drafts via no published publication event), **clinic rep** (owned clinics via active memberships, active referral grants with case status, pending proposals), **coordinator** (assigned case queue via `current_coordinator_id`, awaiting-patient count, open/unassigned support, home-service pending), **owner** (aggregate case-status counts, total/active clinics, verified practitioners, open support, published/pending CMS posts — no clinical detail), **tech admin** (outbox pending via `processed_at IS NULL`, failed notification deliveries, audit 24h, consent records, recent audit — no clinical data). Added missing `case()`/`clinic()` relationships to ReferralProposal + ReferralGrant. 9 feature tests (DashboardTest): guest denied, patient own-cases-only, clinician credential+publish gate, clinician unverified cannot publish, clinic owned-grants only, coordinator queue+awaiting, owner aggregate no clinical detail, tech admin ops health no clinical, every-role returns payload. | Build dashboard admin UI (Blade). | Continue. |

## Activation gates (intentionally open — external)

| Gate | State | Required input |
| --- | --- | --- |
| A — Legal/consent text (fa/ar/en) | BLOCKED_EXTERNAL | Approved exact legal wording in all three languages. |
| B — SMS production | BLOCKED_EXTERNAL | Provider credentials, templates, delivery/callback/retry verification. |
| C — Retention + encrypted backup/restore | BLOCKED_EXTERNAL | Approved retention period + encrypted backup + proven restore drill. |
| D — Staffing | BLOCKED_EXTERNAL | Named coordinator coverage, licensed clinical lead, clinician credential records, accurate 24/7 wording. |
| E — Scanner drills | BLOCKED_EXTERNAL | Operator-approved EICAR + forced-timeout fail-closed drills. |

Intake stays `INTAKE_ENABLED=false` until A–E pass, end-to-end rehearsal
succeeds, and explicit activation approval is given. All independent
technical work continues regardless of these gates.

## Overbuild salvage matrix

Branch `vibe/backend-comprehensive-5ec088` (commit `536f012`). Classified per
the handoff decision.

| Component | Classification | Reason | Action |
| --- | --- | --- | --- |
| Finance — PaymentService, PaymentIntent, PaymentTransaction, PaymentWebhook | FUTURE_REFERENCE | Outside current MVP (no payments). | Leave on old branch; do not port. |
| Finance — LedgerService, LedgerAccount/Entry/Transaction | FUTURE_REFERENCE | Outside current MVP (no ledger). | Leave on old branch. |
| Finance — Refund, SettlementBatch/Item, PayoutAccount, ReconciliationResult | FUTURE_REFERENCE | Outside current MVP. | Leave on old branch. |
| Finance — Order, OrderLine | FUTURE_REFERENCE | Outside current MVP. | Leave on old branch. |
| config/payment.php (Zarinpal/IDPay) | REJECT | Hardcodes payment gateways; exposes payment config. | Do not port. |
| Matching — MatchingService, MatchRun, MatchCandidate, MatchDecision | REJECT | Automatic marketplace matching is an explicit MVP exclusion. | Leave on old branch. |
| Scheduling — SlotService, AppointmentSlot, SlotHold, CapacityWindow, BookingMode, HoldStatus | FUTURE_REFERENCE | Instant slot/hold booking excluded from MVP. | Leave on old branch. |
| Scheduling — Appointment, AppointmentStatusHistory, CheckIn, VisitConfirmation, AvailabilityRule, OperatingHour, Holiday | FUTURE_REFERENCE | Appointment-booking flow outside current coordination scope. | Leave on old branch. |
| Provider models — Clinic, ClinicBranch, ClinicUser, ClinicCredentialing, Dentist, Credential, Service, BranchService | REJECT | `main` already has a **better** provider schema (`clinics`, `practitioners`, `clinic_memberships`) with licence_hash + credential_status + expires_at. Overbuild models are tangled with marketplace relationships (payoutAccounts, settlementItems, ledgerEntries, appointmentSlots) and reference a non-existent `ClinicMembership` (it uses `ClinicUser`). | Build `Clinic`, `Practitioner`, `ClinicMembership` fresh against `main`'s schema. Do not salvage. |
| Provider controllers — ClinicController, ClinicBranchController, DentistController, ServiceController | REJECT | CRUD over the marketplace-tangled models; no branch/service-area/credential workflow matching canonical scope. | Build fresh against `main`'s schema + canonical authorization. |
| Marketplace operations — Complaint, FraudSignal, DocumentVerification, Notification, UrgencyAssessment, PatientPreference, IntakeAnswer, PriceSnapshot | FUTURE_REFERENCE / REJECT | Mixed marketplace semantics; `main` already covers the in-scope equivalents. | Inspect individually only if a canonical need appears; otherwise leave. |
| `Review` model (parent of ReviewRevision) | INSPECT | `main` has `review_revisions` + `publication_events` tables but no `Review`/`PublicationEvent` models. Overbuild's `Review` is marketplace-tangled (requires "verified completed visit"). | Build `Review` + `PublicationEvent` fresh against `main`'s schema with assigned-licensed-clinician-only publish semantics. |
| SupportCase, SupportCaseMessage | REJECT | Marketplace-tangled support; not on `main`. | Build support system fresh per §20. |
| Policies — Appointment/Order/PaymentIntent/Refund/ClinicBranch/Dentist/Service | REJECT | Tied to overbuild models/marketplace. | Build fresh policies for canonical models. |
| DomainServiceProvider | REJECT | Registers marketplace services. | Do not port; `main` registers services in `AppServiceProvider`. |
| config/matching.php, config/scheduling.php | REJECT | Marketplace/scheduling config. | Do not port. |
| Migration `2026_09_09_*_create_{clinic_network,matching,scheduling,finance,operations}_tables` | REJECT | Creates marketplace tables not on `main`. `main`'s own migrations are canonical. | Do not port; `main`'s schema is the source of truth. |

Net salvage from overbuild: **none ported wholesale.** Any reusable *idea*
(e.g. the shape of a credential record) is reimplemented against `main`'s
already-superior schema. The branch is preserved as historical/future
reference; no feature flags are needed because the overbuild code is not
entering the production code path.

## Roadmap (execution order)

0. ~~Branch from `main` → `feat/royadarman-completion`.~~ ✅
0. ~~Correct superseded docs (root README + ADRs).~~ ✅ (pending commit)
0. ~~This status file.~~ ✅ (pending commit)
0. ~~Branch from `main` → `feat/royadarman-completion`.~~ ✅
0. ~~Correct superseded docs (root README + ADRs).~~ ✅ committed
0. ~~This status file.~~ ✅ committed
0. ~~Establish PHP 8.3 + Composer; run `composer install`, `artisan about`,
   `route:list`, `test`, `composer audit`, Pint.~~ ✅ (PHP 8.4 dev sandbox;
   25 tests/81 assertions pass; Pint PASS; composer audit clean)
0. ~~Read-only production comparison via Royadarman MCP.~~ ✅ production ==
   main; healthy; intake disabled; no overbuild deployed.
1. Fix any baseline defects found (e.g. missing `Review`/`PublicationEvent`/
   `Clinic`/`Practitioner`/`ClinicMembership` models for tables that already
   exist on `main`; repo has no `.env.example`).
2. Auth/authorization hardening: extend cross-record denial tests to every
   role; audit list-query scope; verify kill-switch on every intake route.
3. Data/workflow integrity: cases, transitions, consent, provider network
   models, referral, home dentistry lifecycle, support system.
4. OPG pipeline end-to-end verify against MariaDB + ClamAV; retention hooks.
5. CMS (vertical): migrations → models → services → policies → requests →
   resources → routes → admin UI → multilingual → SEO → media → revisions →
   tests → browser.
6. Role dashboards (real UI, clinical scope, no earnings/settlement/payment).
7. Multilingual completeness (fa/ar/en, RTL/LTR, legal fail-closed).
8. Public frontend + SEO (sitemap, robots, hreflang, canonical, structured
   data, redirects) keeping approved design.
9. SMS adapter (provider abstraction, no hard-coded provider) + ClamAV
   adapter; do not activate payments.
10. Quality hardening: tests (incl. MariaDB integration), security/IDOR,
    browser QA, accessibility, performance, dependency audit, TODO/stub scan.
11. Production drift reconciliation: backups, source comparison, deployment
    plan, migrations, worker/scheduler, cache, health, rollback.
12. Deploy technically-complete release with `INTAKE_ENABLED=false`; verify
    every route for that state.
13. ClickUp + docs + memory reconciliation.
14. Activation gates: do everything possible automatically; mark external
    items `BLOCKED_EXTERNAL` with exact required input.
15. End-to-end rehearsal (only when gates permit).
16. Controlled intake activation (only after explicit approval).
17. Post-activation verification; rollback ready.
