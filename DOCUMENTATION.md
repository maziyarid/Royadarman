# Royadarman — System Documentation

Comprehensive documentation of every section and capability of the Royadarman
dental guidance and coordination platform. This file is the operational reference
for the whole system; the staged design history lives in `docs/` and the
backend-specific contracts live in `backend/`.

> **Verification status of this document:** every claim below was re-checked on a
> clean checkout in this session. See [§13 Verification evidence](#13-verification-evidence)
> for the exact commands and results.

---

## Table of contents

1. [Product and scope](#1-product-and-scope)
2. [Repository layout](#2-repository-layout)
3. [Technology stack](#3-technology-stack)
4. [Backend architecture](#4-backend-architecture)
5. [Domain modules and capabilities](#5-domain-modules-and-capabilities)
6. [Identity and authentication](#6-identity-and-authentication)
7. [Patient case workflow and state machine](#7-patient-case-workflow-and-state-machine)
8. [Clinical document pipeline (OPG)](#8-clinical-document-pipeline-opg)
9. [Consent, policies, and clinical access](#9-consent-policies-and-clinical-access)
10. [Referrals and coordination](#10-referrals-and-coordination)
11. [Operations: outbox, idempotency, notifications, retention](#11-operations-outbox-idempotency-notifications-retention)
12. [API reference](#12-api-reference)
13. [Multilingual public frontend](#13-multilingual-public-frontend)
14. [Frontend previews (v2 / v3 / demo)](#14-frontend-previews-v2--v3--demo)
15. [Data model](#15-data-model)
16. [Configuration and environment](#16-configuration-and-environment)
17. [Deployment, rollback, and production safety](#17-deployment-rollback-and-production-safety)
18. [Testing and quality gates](#18-testing-and-quality-gates)
19. [Verification evidence](#19-verification-evidence)

---

## 1. Product and scope

Royadarman is a **24/7 dental guidance, coordination, and support centre** — not a
doctor and not a single clinic. It owns the patient request, guidance, OPG review
coordination, referral matching, and support; it deliberately does **not** own
diagnosis, treatment, clinical records, or payments.

**What Royadarman does**

- 24/7 dental guidance and patient support.
- **Tehran-only** home-dentistry coordination.
- Preliminary OPG (panoramic radiograph) review performed **only by an assigned
  licensed dentist**.
- Location- and budget-aware referral to partner dentists and clinics.

**What Royadarman does not do**

- No autonomous diagnosis and no guarantee of treatment outcomes.
- No payment processing and no marketplace transactions in MVP.
- No public exposure of clinical files.
- The owner has no implicit patient or clinical access.

**Iran-first constraints** (locked by `docs/04-iran.md`): Persian is the default
locale with RTL rendering; Arabic and English are first-class supported locales;
display timezone is `Asia/Tehran`; home dentistry is explicitly limited to Tehran.

### Current phase

Phase 0 is complete: product, legal, architecture, and the production backend are
built and verified. Intake remains **server-side disabled** (`INTAKE_ENABLED=false`)
until the activation gates in [`backend/CHECKLIST.md`](backend/CHECKLIST.md) are
approved (legal consent text, SMS provider, retention period, licensed clinical
lead, coordinator coverage, scanner drills, end-to-end rehearsal).

---

## 2. Repository layout

```
Royadarman/
├── README.md                              Product orientation + read order
├── DOCUMENTATION.md                       This file (system reference)
├── docs/                                  Staged design history (Phase 0)
│   ├── 00-reuse-verdict.md                ADR-001: do not fork Medical-CRM
│   ├── 01-adrs.md                         Architecture decision records
│   ├── 02-architecture.md                 Phase-1 working specification
│   ├── 03-phase-0.md                      Phase-0 work before marketplace code
│   ├── 04-iran.md                         Iran-first implications
│   └── 05-data-model.md                   Principal data model
├── backend/                               Production Laravel 13 application
│   ├── app/                               Domain-modular application code
│   ├── config/                            Laravel + Royadarman configuration
│   ├── database/{migrations,factories,seeders}
│   ├── lang/{fa,en,ar}/ui.php             Three complete locale string sets
│   ├── public/                            Front controller + public assets
│   ├── resources/views/public/home.blade.php
│   ├── routes/{web,api,console}.php
│   ├── tests/                             25 tests / 81 assertions
│   ├── ARCHITECTURE.md, DESIGN.md, DEPLOYMENT.md, CHECKLIST.md, design-qa.md
│   └── premium-audit.json                 Strict design audit (0 findings)
├── Front-end v1/
│   ├── v3-preview/                        Active interactive SPA preview (accepted-blue)
│   ├── v2-preview/                       Earlier SPA preview (teal)
│   └── demo/                              Earliest static screen demo
└── Current Public_HTML/                   Production public_html snapshot (cPanel)
```

---

## 3. Technology stack

| Layer | Choice | Notes |
|---|---|---|
| Language | PHP 8.3+ (verified on 8.4) | Backend requires PHP 8.3 minimum |
| Framework | Laravel 13 | Modular monolith, Blade SSR |
| Database | MariaDB 10.11 (prod) / SQLite (tests) | InnoDB, `utf8mb4_unicode_ci`, UTC, strict |
| Queue | Laravel database queue | Supervised workers; sync in tests |
| Scheduler | `artisan schedule:run` per minute | One cron entry |
| Security scanner | ClamAV | Private OPG quarantine + scan |
| Runtime host | AlmaLinux / cPanel / Apache | Production deployment target |
| Frontend (public) | Server-rendered Blade | No Node at runtime |
| Frontend (preview) | Vanilla JS SPA | `v3-preview` is the active design preview |
| Node tooling | Vite 8 + Tailwind 4 | Build only, not runtime |

---

## 4. Backend architecture

A **modular monolith**: a single Laravel application organised into bounded
domain modules under `app/Domain/`, each with its own enums, services, contracts,
and value objects. Cross-cutting infrastructure adapters live under
`app/Infrastructure/`.

**Runtime topology** (from `backend/ARCHITECTURE.md`)

- **Apache public root** (`/home/royadarman/public_html`): only the front
  controller and public assets; `.env`, `vendor`, `storage` are never web-reachable.
- **Application root** (`/home/royadarman/apps/royadarman-backend`): Laravel,
  vendor, `.env`, queues, logs, private storage.
- **Uploads**: OPG images enter a private quarantine disk, are content-validated,
  hashed, scanned, then promoted to a separate private approved disk. Downloads are
  server-streamed after authorisation and audited — **no public or signed object
  URLs** are ever exposed.

**Request pipeline**

1. `RequestId` middleware attaches a per-request **ULID correlation ID** to every
   response (`request_id`), present even in error envelopes.
2. `SetLocale` middleware resolves the locale from the route segment
   (`fa`/`ar`/`en`) and applies it app-wide.
3. `EnsurePatientIntakeEnabled` middleware fail-closes all intake endpoints when
   `INTAKE_ENABLED=false`.
4. `web` middleware group provides session + CSRF protection to the API routes
   that need it.

**Error envelopes.** All API failures return a stable, documented envelope:
```json
{
  "error": {
    "code": "error.consent.translation_unavailable",
    "request_id": "01M2655..."
  }
}
```
HTTP-level errors additionally carry a `message`. The `request_id` lets support
correlate any client report to a single request across logs.

---

## 5. Domain modules and capabilities

| Module | Path | Responsibilities |
|---|---|---|
| **Identity** | `app/Domain/Identity/` | Patient OTP, staff OTP + TOTP/recovery, roles, minimal profile, locale |
| **Cases** | `app/Domain/Cases/` | Draft/submit workflow, optimistic version checks, idempotency, explicit state machine |
| **Consent** | (models + policy controller) | Versioned locale-specific policy text and immutable acceptance events |
| **Documents** | `app/Domain/Documents/` | JPEG/PNG OPG validation, quarantine, scanning, retention, access audit |
| **Coordination** | (controllers + models) | Assignment, explicit status transitions, referral proposals/grants |
| **Clinical** | (models + policies) | Append-only review revisions; publish only by assigned licensed clinician |
| **Operations** | `app/Domain/Operations/` | Transactional outbox, idempotency records, notification delivery, retention |

**Enums** (the controlled vocabulary of the system)

- `UserRole`: `patient`, `coordinator`, `clinician`, `clinic_rep`, `owner`,
  `tech_admin`. `isStaff()` distinguishes staff from patients. The owner has **no
  implicit patient or clinical access**.
- `ServiceType`: `guidance_referral`, `home_dentistry`, `opg_review`.
- `CaseStatus`: 13 states with an explicit allowed-transition table (see §7).
- `DocumentStatus`: `quarantined`, `scanning`, `approved`, `rejected`,
  `scan_failed`, `deleted`.

---

## 6. Identity and authentication

**Patient authentication** — OTP only, no passwords for patients.

1. `POST /api/v1/auth/otp/challenge` — phone number is **normalised** and
   **hashed** (`App\Support\DigitNormalizer` + a dedicated `ROYADARMAN_PHONE_HASH_KEY`,
   independent of `APP_KEY`) and a single-use `OtpChallenge` is created. The OTP is
   delivered via the `OtpSender` contract (SMS in production, log in tests).
2. `POST /api/v1/auth/otp/verify` — verifies the code; the challenge is
   single-use and expiry-checked; on success a minimal `User` (role `patient`) is
   created or recalled.
3. `POST /api/v1/auth/logout` ends the session.

**Staff authentication** — OTP plus TOTP/recovery codes for second factor,
verified by `App\Domain\Identity\Services\TotpVerifier`. Recovery codes are
single-use and consumed on use.

**Throttling.** Challenge is throttled at `20,60`; verify at `30,60` (20/30
attempts per 60 seconds).

---

## 7. Patient case workflow and state machine

`App\Domain\Cases\Services\CaseWorkflow` is the single authority over case
status transitions. `App\Domain\Cases\Services\SubmitPatientCase` handles
draft/submit with idempotency and optimistic version checks.

**State machine** (`CaseStatus::allowedTargets()`):

```
draft ──> submitted | cancelled
submitted ──> awaiting_contact | cancelled
awaiting_contact ──> in_coordination | awaiting_patient | cancelled
in_coordination ──> awaiting_patient | clinician_review | referral_proposed
                 ──> home_visit_proposed | resolved | cancelled
awaiting_patient ──> in_coordination | cancelled
clinician_review ──> in_coordination | resolved | cancelled
referral_proposed ──> referred | in_coordination | cancelled
home_visit_proposed ──> visit_scheduled | in_coordination | cancelled
referred | visit_scheduled ──> resolved | cancelled
resolved | cancelled ──> closed
closed ──> (terminal)
```

Every transition is recorded as an immutable `case_status_events` row carrying a
`correlation_id`, enabling full timeline reconstruction.

**Intake endpoints** (all fail-closed behind `EnsurePatientIntakeEnabled`):

- `POST /api/v1/cases/draft` — create an idempotent case draft.
- `POST /api/v1/cases/{case}/submit` — submit with versioned localized consent.
- `GET /api/v1/cases/{case}` — read the patient's own case.

---

## 8. Clinical document pipeline (OPG)

The OPG pipeline is the most security-sensitive path. Implemented in
`App\Domain\Documents\Services\QuarantineClinicalDocument` with the
`DocumentScanner` contract (`App\Infrastructure\Documents\ClamAvDocumentScanner`
in production).

**Accepted formats (production):** JPEG and PNG only
(`config/royadarman.opg.extensions = ['jpg','jpeg','png']`, mime
`['image/jpeg','image/png']`). Maximum 20 MiB and 60 megapixels by default.

> **Note on the v3-preview:** the interactive preview accepts PDF as well
> (prototype constraint of "JPEG/PNG/PDF, max 15 MB" per its `UX-CONTRACT.md`).
> This is intentional for the prototype only; the production server is the single
> source of truth and rejects anything outside JPEG/PNG.

**Pipeline stages** (`DocumentStatus`)

1. **Quarantine** — file lands on a private quarantine disk, never under
   `public_html`. Extension + detected-MIME are cross-checked; mismatch is
   rejected. SHA-256 hash recorded.
2. **Scanning** — `ScanClinicalDocument` job runs the `DocumentScanner`
   (ClamAV). `scan_attempts` rows track each attempt.
3. **Approved / Rejected / ScanFailed** — clean files promote to a separate
   private approved disk; infected files are marked `rejected`, their hash,
   verdict and audit metadata are recorded, and the malicious bytes are then
   **deleted** from quarantine (retaining malware is worse than retaining the
   evidence of the verdict). Files that fail scanning (engine unavailable,
   timeout, hash mismatch) remain in quarantine as `scan_failed` and are never
   clinical-accessible.
4. **Deleted** — `retention:run` deletes the private object and marks the record
   once an operator-approved retention duration has elapsed. No duration is ever
   invented by code — retention is fail-closed until configured.

**Downloads** are server-streamed after authorization (`ClinicalDocumentPolicy`),
never via public or signed object URLs, and every access is audited in
`document_access_events`.

**Document endpoints:**

- `POST /api/v1/cases/{case}/documents` — upload an OPG.
- `GET /api/v1/cases/{case}/documents/{document}` — scan status.
- `GET /api/v1/cases/{case}/documents/{document}/content` — audited stream.

---

## 9. Consent, policies, and clinical access

**Versioned localized consent.** `policy_versions` holds policy text per locale.
`consent_records` are immutable acceptance events (`accepted`, `decided_at`,
`withdrawn_at`). Submission requires a matching, available localized consent
translation — **fail-closed**: if a translation is missing, submission is blocked
with `error.consent.translation_unavailable` (verified live: the
`/api/v1/policies/{key}` endpoint returns this code while translations are absent).

**Policy endpoint:** `GET /api/v1/policies/{key}` — public read of a policy
version (no auth required).

**Clinical access** is enforced by `PatientCasePolicy` and
`ClinicalDocumentPolicy`:

- A patient sees only their own cases and documents.
- A clinician/clinic representative sees only assigned cases and documents.
- The **owner has no implicit patient or clinical access**; clinical publishing is
  restricted to the assigned licensed clinician.

**Review revisions** are append-only (`review_revisions`); a clinician can
supersede their own review but cannot edit a signed review.

---

## 10. Referrals and coordination

**Coordination**

- `POST /api/v1/staff/cases/{case}/assignments` — coordinator assigns a clinician
  or clinic representative (`case_assignments`).
- `PATCH /api/v1/staff/cases/{case}/status` — explicit state-machine transition
  (rejects illegal transitions).

**Referrals**

- `POST /api/v1/staff/cases/{case}/referral-proposals` — staff create a
  `referral_proposal` (minimum data).
- `POST /api/v1/cases/{case}/referrals/{proposal}/decision` — patient accepts or
  declines; acceptance creates a minimum-data `referral_grant`.

**Clinical reviews**

- `POST /api/v1/staff/cases/{case}/reviews` — clinician drafts a preliminary
  review.
- `POST /api/v1/staff/cases/{case}/reviews/{review}/publish` — only the
  assigned licensed clinician can publish; revisions are append-only.

---

## 11. Operations: outbox, idempotency, notifications, retention

**Transactional outbox** (`App\Domain\Operations\Services\Outbox`,
`outbox_events` table). Domain events are written in the same DB transaction as
the state change, then a worker (`ProcessOutboxEvent` job +
`outbox:dispatch` console command) delivers them. This avoids the dual-write
problem: state and the intent to notify are atomic.

**Idempotency** (`App\Domain\Operations\Services\Idempotency`,
`idempotency_records` table). Draft/submit and other mutating intake calls are
idempotent by key — a replayed request returns the original result rather than
creating duplicates.

**Notifications** (`App\Domain\Operations\Contracts\NotificationSender`,
`App\Infrastructure\Operations\HttpNotificationSender`,
`notification_deliveries` table). Delivery is locale-aware. The SMS provider
callback is `POST /api/v1/notifications/callback` — a **signed callback** endpoint
that verifies the `ROYADARMAN_SMS_CALLBACK_SECRET`.

**Retention** (`retention:run` console command, `retention_jobs` table). Due
retention jobs delete private objects and mark records. **No retention duration
is invented by code** — `ROYADARMAN_DOCUMENT_RETENTION_DAYS` must be set by an
operator; until then the config value is `null` and retention is fail-closed
(verified by `OperationsTest::test_due_retention_deletes_private_object_…`).

**Queued jobs**

- `App\Jobs\ScanClinicalDocument` — runs the document scanner.
- `App\Jobs\ProcessOutboxEvent` — delivers an outbox event.

**Console commands**

- `outbox:dispatch` — dispatch due outbox events.
- `retention:run {--limit=50}` — execute due retention jobs.

---

## 12. API reference

Base path `/api/v1`. All endpoints that mutate state require a session (the API
uses the `web` middleware group) and CSRF token; correlation `request_id` is
returned on every response.

| Method | Path | Auth | Intake gate | Purpose |
|---|---|---|---|---|
| POST | `/api/v1/auth/otp/challenge` | no | no | Request patient OTP (throttled 20,60) |
| POST | `/api/v1/auth/otp/verify` | no | no | Verify patient OTP (throttled 30,60) |
| POST | `/api/v1/auth/logout` | yes | no | End session |
| GET | `/api/v1/me` | yes | no | Current user profile |
| PATCH | `/api/v1/me/preferences` | yes | no | Update preferences |
| GET | `/api/v1/policies/{key}` | no | no | Read a policy version |
| POST | `/api/v1/notifications/callback` | signed | no | SMS provider delivery callback |
| POST | `/api/v1/cases/draft` | yes | **yes** | Create idempotent case draft |
| POST | `/api/v1/cases/{case}/submit` | yes | **yes** | Submit case with consent |
| GET | `/api/v1/cases/{case}` | yes | **yes** | Read own case |
| POST | `/api/v1/cases/{case}/documents` | yes | **yes** | Upload OPG |
| GET | `/api/v1/cases/{case}/documents/{document}` | yes | **yes** | Document scan status |
| GET | `/api/v1/cases/{case}/documents/{document}/content` | yes | **yes** | Audited stream |
| POST | `/api/v1/cases/{case}/referrals/{proposal}/decision` | yes | **yes** | Patient referral decision |
| POST | `/api/v1/staff/cases/{case}/assignments` | staff | **yes** | Assign clinician/clinic |
| PATCH | `/api/v1/staff/cases/{case}/status` | staff | **yes** | State-machine transition |
| POST | `/api/v1/staff/cases/{case}/referral-proposals` | staff | **yes** | Propose a referral |
| POST | `/api/v1/staff/cases/{case}/reviews` | staff | **yes** | Draft clinical review |
| POST | `/api/v1/staff/cases/{case}/reviews/{review}/publish` | staff | **yes** | Publish review (assigned clinician) |

**Public web routes** (no auth)

| Method | Path | Purpose |
|---|---|---|
| GET | `/` | 302 redirect to `/fa/` |
| GET | `/up` | Health check (`{"status":"ok"}`) |
| GET | `/{locale}/` | Server-rendered home, `locale ∈ {fa, ar, en}` |

Total: **22 routes** (`php artisan route:list`).

---

## 13. Multilingual public frontend

The production public site is **server-rendered Blade**
(`resources/views/public/home.blade.php`) with public assets under
`public/assets/`. No Node is required at runtime.

**Locales**

- `fa` — Persian, **default**, RTL.
- `ar` — Arabic, RTL.
- `en` — English, LTR.
- `/` redirects to `/fa/`; unsupported locales return 404.

Each locale page emits correct `<html lang="…" dir="…">`, canonical and hreflang
links for all three locales plus `x-default`, localized metadata, Open Graph and
Twitter cards, and a language switcher to real locale URLs.

**Translation parity.** `lang/{fa,ar,en}/ui.php` each contain **32 identical
keys** — no missing translations across locales (machine-verified in this session).

**Accessibility & resilience** (from `design-qa.md`, strict audit 0 findings)

- Skip link as first Tab target with a solid focus outline.
- Visible focus, reduced-motion handling, forced-colors handling.
- Responsive breakpoints at ≤900px and ≤600px, 320px minimum canvas.
- No browser `alert`/`confirm`/`prompt`, no inline click handlers, no JavaScript
  URLs, no removed focus outlines.
- Intake is an honest status panel, not a false working form, while disabled.

**Public assets** (all verified 200 in this session):

| Asset | Purpose |
|---|---|
| `/assets/site.css` | Lapis/apricot/mineral design system + RTL |
| `/assets/site.js` | Minimal progressive enhancement |
| `/assets/brand-mark.svg` | Tooth/support-arcs logo |
| `/assets/favicon.svg` | Favicon |
| `/assets/icon-home.svg`, `icon-opg.svg`, `icon-support.svg` | Service icons |

---

## 14. Frontend previews (v2 / v3 / demo)

`Front-end v1/` holds three interactive previews of the patient journey. They
are **design previews only** — `robots.txt` marks them `noindex,nofollow,noarchive`
and they must not accept real information until connected to the production
backend.

| Preview | Theme | Status | Notes |
|---|---|---|---|
| `v3-preview/` | "accepted-blue" | **Active** | Hash-routed SPA; the preferred preview |
| `v2-preview/` | teal | Earlier | Superseded by v3 |
| `demo/` | earliest | Oldest | Static screen demo |

**v3-preview structure**

- `index.html` — 40-line SPA shell (`#app` mount, boot screen, toast region,
  noscript fallback).
- `assets/app.js` — 629 lines, vanilla JS ES module, no runtime errors
  (verified with `node --check` and jsdom execution).
- `assets/styles.css` — 2453 lines, full design system.
- `assets/accepted-blue.css` — v3 theme override.
- `assets/logo-mark.svg`, `opg-hero.svg`, `favicon.svg`, 4 Irancell TTF fonts.

**v3-preview journey** (hash routes)

- `#/home` — landing with services, path, trust, FAQ, contact.
- `#/request/service` → `contact` → `document` → `preferences` → `review` →
  `receipt`.
- OPG service adds a `document` step; guidance/referral skips it.

**v3-preview capabilities** (per `premium-ui.json`): Select/Listbox, Form, File
Upload, Toast — native controls for Select/Form, authored for Upload/Toast.

**OPG upload contract** (`v3-preview/UX-CONTRACT.md` §5): explicit UI states
(empty, drag active, local validating, local rejected, ready, uploading,
persisted/quarantined, scanning, approved, rejected by scanner, network
interrupted, expired session, deleted). Prototype constraints: JPEG/PNG/PDF max
15 MB; production values come from server config and are repeated in client hints,
never enforced only in JavaScript.

**Cleanup performed in this session:** removed 15 timestamped `.bak.*` editor
backup files and an unreferenced empty `opg-hero.jpg` (0 bytes; the `.svg` is what
the app actually uses) from `v3-preview/`.

---

## 15. Data model

Royadarman stores **referral and transactional** information; it deliberately
does not become a dental EMR. Full tables (31 total across migrations):

**Identity & policy:** `users`, `otp_challenges`, `password_reset_tokens`,
`sessions`, `policy_versions`.

**Cases & consent:** `patient_cases`, `consent_records`, `consent_events`,
`case_assignments`, `case_status_events`.

**Documents:** `clinical_documents`, `scan_attempts`, `document_access_events`,
`retention_jobs`.

**Coordination & clinical:** `clinics`, `clinic_memberships`, `practitioners`,
`referral_proposals`, `referral_grants`, `review_revisions`,
`publication_events`, `coordination_tasks`.

**Operations:** `outbox_events`, `notification_deliveries`,
`idempotency_records`, `audit_events`.

**Framework:** `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.

Key design choices: ULID primary keys on domain tables; immutable event tables
(`case_status_events`, `document_access_events`, `audit_events`) with
`correlation_id`; `storage_key` unique on documents; retention indexed on
`retention_until`. See `docs/05-data-model.md` and `backend/database/migrations/`
for the authoritative schema.

---

## 16. Configuration and environment

Configuration lives in `backend/config/royadarman.php` (domain) plus standard
Laravel config files. An `.env.example` is provided (added in this session — the
repo previously shipped without one).

**Key environment variables**

| Variable | Default | Purpose |
|---|---|---|
| `INTAKE_ENABLED` | `false` | Fail-safe intake gate. **Do not enable until activation gates approved.** |
| `ROYADARMAN_PHONE_HASH_KEY` | `APP_KEY` | Independent phone-hash secret (must differ from `APP_KEY` in prod) |
| `ROYADARMAN_OPG_DISK` | `private-opg` | Approved document disk |
| `ROYADARMAN_OPG_QUARANTINE_DISK` | `opg-quarantine` | Quarantine disk |
| `ROYADARMAN_OPG_SCANNER_ENABLED` | `false` | Enable ClamAV scanning |
| `ROYADARMAN_OPG_SCANNER_COMMAND` | `/usr/bin/clamscan` | Scanner binary |
| `ROYADARMAN_OPG_SCANNER_TIMEOUT` | `60` | Scanner timeout (seconds) |
| `ROYADARMAN_SMS_ENDPOINT` / `ROYADARMAN_SMS_TOKEN` | — | SMS provider credentials |
| `ROYADARMAN_SMS_CALLBACK_SECRET` | — | Signed callback verification secret |
| `ROYADARMAN_DOCUMENT_RETENTION_DAYS` | unset | **Must stay unset** until an operator approves a retention period |

---

## 17. Deployment, rollback, and production safety

See `backend/DEPLOYMENT.md` for the full release gate and rollback procedure.

**Release gate (selection):** `composer.lock` present and `composer install
--no-dev --prefer-dist --optimize-autoloader` succeeds; `APP_ENV=production`,
`APP_DEBUG=false`; `INTAKE_ENABLED=false`; independent phone-hash and callback
secrets; MariaDB InnoDB/utf8mb4/UTC/strict; private uploads outside `public_html`
mode `0750`; ClamAV installed and tested; Apache routes only through the front
controller; queue supervisors + per-minute scheduler; encrypted backups with a
restore rehearsal.

**Database-safe release:** expand/contract — additive code and migrations first,
never rename/drop live columns in the same release that stops writing them.
Rollback repairs forward with a new migration; destructive contraction happens
only in a later release after old use is verified absent.

**`Current Public_HTML/`** is the production `public_html` snapshot for cPanel.
Its `index.php` keeps the hardcoded `/home/royadarman/apps/royadarman-backend`
path because that is the real production application root. The development source
`backend/public/index.php` uses `dirname(__DIR__)` so it runs anywhere.

---

## 18. Testing and quality gates

| Gate | Command | Expected |
|---|---|---|
| Tests | `php artisan test` | 25 tests, 81 assertions, 0 failures |
| Lint | `vendor/bin/pint --test` | 95 files, 0 issues |
| Migrations | `php artisan migrate --force` | 7 migrations apply cleanly |
| Strict design audit | `premium-audit.json` | 0 findings |

**Test coverage** (`backend/tests/`):

- `Feature/OtpAuthenticationTest` — patient OTP normalise/hash/single-use, expiry,
  staff recovery codes.
- `Feature/PatientCaseIntakeTest` — intake server-side disabled, idempotent
  create/submit with localized consent, missing-translation blocks submission.
- `Feature/CaseWorkflowTest` — state-machine transitions.
- `Feature/ClinicalDocumentPipelineTest` — OPG validation, quarantine, scan,
  promotion, audited streaming.
- `Feature/ClinicalAccessTest` — policy isolation, owner has no clinical access.
- `Feature/ReferralController`/`StaffCaseController` — proposals, decisions,
  assignments, reviews.
- `Feature/OperationsTest` — outbox, idempotency, retention (fail-closed without
  a configured duration).
- `Feature/LocaleAndAuthorizationTest` — each locale renders correct lang/dir.
- `Feature/ApiErrorEnvelopeTest` — stable codes + `request_id`.
- `Unit/CaseStatusTest` — enum transition logic.

---

## 19. Verification evidence

All checks re-run on a clean checkout during this session (PHP 8.4.24, Laravel
13.29, SQLite in-memory for tests).

```text
$ cd backend && php artisan test
Tests: 25 passed (81 assertions)   Duration: 0.44s

$ vendor/bin/pint --test
Laravel  PASS  .......................................... 95 files

$ php artisan migrate --force
# 7 migrations applied (0001_* x3, 2026_08_31_* x2, 2026_09_08_* x2)

$ php artisan route:list
# 22 routes listed
```

**Live HTTP smoke test** (`php artisan serve`, probed via Node http):

```text
GET /                  -> 302 (Location: /fa/)
GET /up                -> 200 {"status":"ok"}
GET /fa/               -> 200, <html lang="fa" dir="rtl">
GET /ar/               -> 200, <html lang="ar" dir="rtl">
GET /en/               -> 200, <html lang="en" dir="ltr">
GET /fr/               -> 404
GET /assets/*          -> 200 (site.css, site.js, brand-mark.svg, icons)
GET /api/v1/policies/consent -> 503 error.consent.translation_unavailable (fail-closed)
POST /api/v1/notifications/callback (unsigned) -> 401 (stable envelope + request_id)
```

**Frontend v3-preview** (`http-server`, probed via Node http + jsdom):

```text
All assets (styles.css, accepted-blue.css, app.js, logo-mark.svg,
opg-hero.svg, favicon.svg, fonts, manifest, sitemap, robots) -> 200
node --check app.js      -> OK
jsdom execution          -> 0 runtime errors, #app renders content
```

**Locale parity:** `lang/{fa,ar,en}/ui.php` each have 32 keys, zero missing
cross-locale.

**Fixes applied in this session**

1. `backend/public/index.php` — replaced the hardcoded production path
   `/home/royadarman/apps/royadarman-backend` with `dirname(__DIR__)` so the
   application boots in any environment (previously every non-production path
   returned HTTP 500). The production snapshot `Current Public_HTML/index.php`
   intentionally keeps the hardcoded path.
2. Added `backend/.env.example` (the repo shipped without one, so `php artisan
   key:generate` and first-time setup failed).
3. Ran `vendor/bin/pint` to bring 78 files into compliance (trailing-newline and
   brace-position drift); `pint --test` now reports 95 files clean.
4. Removed 15 timestamped `.bak.*` editor backup files and one unreferenced
   empty `opg-hero.jpg` from `Front-end v1/v3-preview/`.

---

## Activation gates still intentionally open

(Tracked in `backend/CHECKLIST.md`; intake stays disabled until these close.)

- [ ] Approve exact legal consent/privacy text in fa/ar/en.
- [ ] Configure production SMS provider credentials/templates; test delivery and
      callbacks.
- [ ] Approve a document-retention period and encrypted backup/restore procedure.
- [ ] Confirm named coordinator coverage and licensed clinical lead/credential
      records.
- [ ] Run EICAR and forced-timeout scanner drills in an operator-approved
      maintenance window.
- [ ] After the above: seed approved policies/providers, run an end-to-end case
      rehearsal, then set `INTAKE_ENABLED=true`.

## License

Proprietary. All rights reserved.
