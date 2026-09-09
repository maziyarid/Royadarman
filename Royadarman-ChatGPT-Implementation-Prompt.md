# Royadarman — Complete Backend Implementation Prompt for ChatGPT

> Paste this entire document into a fresh ChatGPT session (o3 or GPT-4o recommended).
> Work through phases in order. Ask for each phase explicitly after the previous one is complete.
> Do not skip phases or combine them — each phase has a verification gate.

---

## PROJECT CONTEXT (read before generating any code)

You are implementing **Royadarman**, a dental patient-support and referral service for Tehran,
Iran. The product provides:

- 24-hour coordination and support (not a live clinical line)
- Home dentistry scheduling (Tehran service area only)
- OPG (orthopantomogram) radiograph review by licensed clinicians
- Budget-aware referrals to partner clinics

**The owner is not a dentist.** All clinical opinions must be attributable to a named, licensed
clinician. Never generate code that allows a non-clinician to publish clinical text.

---

## STACK — non-negotiable

| Layer | Choice | Constraint |
|---|---|---|
| Framework | Laravel 13.x | Pin `composer.lock`; use PHP 8.3 initially |
| Database | MariaDB 10.11, InnoDB, `utf8mb4_unicode_ci` | Foreign keys and transactions required |
| Queues | Database-backed (`QUEUE_CONNECTION=database`) | Redis only if measured load demands it later |
| Rendering | Server-rendered Blade with progressive enhancement | No SPA, no separate frontend rewrite |
| Auth UI | Same-origin routes | No cross-origin auth complexity |
| Host | AlmaLinux/cPanel | Route via Apache/cPanel, not nginx config snippets |
| Node | Build-time only (`npm run build`) | No always-running Node in production |

**MVP exclusions — generate no code for these:**
payments, marketplace/bidding, AI diagnosis, full EHR, medical tourism,
DICOM support, dependent/minor patient intake, guardian proxy flows.

---

## MULTILINGUAL CONTRACT

Three first-class locales. Persian is default.

| Locale | `lang` | `dir` | URL prefix | Switcher label |
|---|---|---|---|---|
| Persian | `fa` | `rtl` | `/fa/` | فارسی |
| Arabic | `ar` | `rtl` | `/ar/` | العربية |
| English | `en` | `ltr` | `/en/` | English |

**Implementation rules — enforce all of these:**

1. Use stable translation keys (`case.status.submitted`, not literal sentences). All domain codes,
   role names, and table names are language-neutral English.
2. Render `lang` and `dir` on the **initial HTML response** (not via JS). Use logical CSS
   properties: `margin-inline-start`, `padding-inline`, `inset-inline-end`, `text-align: start`.
   Do not globally mirror the DOM.
3. Mirror directional navigation arrows and step indicators for RTL. Do NOT mirror: logo,
   radiograph images, medical annotations, phone numbers, clocks, non-directional icons.
4. Use `dir="auto"` on appropriate user-authored text fields. Use `<bdi>` for inline
   unknown-direction content. Keep email, phone, OTP and technical identifiers in isolated LTR
   fields (`dir="ltr"` on the `<input>`).
5. Preserve entered form draft data across a locale switch (server-side draft for authenticated
   users; file inputs require explicit re-selection — explain this in UI copy).
6. Normalize Persian digits (۰-۹), Arabic-Indic digits (٠-٩), and Latin digits (0-9) on the
   **server** for phone, OTP, and budget fields. Preserve originals in stored text.
7. Store all timestamps as UTC. Display as `Asia/Tehran` (UTC+3:30/+4:30 DST-aware). Support
   Persian calendar display; do not assume Arabic → Hijri calendar.
8. Store money as **integer units** with explicit currency column (`IRR`) and input-unit metadata
   (toman vs IRR). Never silently convert on locale switch.
9. Use one plural/message-format library across PHP and JS. Implement Arabic plural rules
   (zero/one/two/few/many/other). Do not concatenate translated fragments.
10. **Consent translations are blocking:** if an approved translation for the current locale is
    missing, block that consent flow entirely. Log ordinary UI string fallbacks; never fall back
    silently on consent or clinical wording.
11. Record `source_language` on clinician notes and patient free text. Do not auto-translate
    clinical records with any AI service.
12. Queue notifications with the **recipient's locale at enqueue time**. Do not rely on the worker
    process's current request locale.
13. Add `hreflang` and canonical URL links for all public pages. Locale must vary public cache;
    private responses must carry `Cache-Control: no-store, private`.

---

## DATA MODEL — 8 modules

Implement as a **Laravel modular monolith**. Suggested namespace structure:
`App\Modules\{Identity|Provider|Intake|Consent|Documents|Coordination|Clinical|Operations}`.

### Module 1: Identity

```
users
  id (ulid), phone_e164, phone_lookup_digest (keyed HMAC, not plain hash),
  phone_encrypted (reversible encryption), display_name_encrypted,
  preferred_contact_locale (fa|ar|en), ui_locale (fa|ar|en),
  preferred_calendar (persian|gregorian), role (patient|coordinator|clinician|clinic_rep|owner|tech_admin),
  is_active, created_at, updated_at

otp_challenges
  id (ulid), user_id FK, verifier_hash (bcrypt of OTP, never plaintext),
  purpose (login|phone_change|registration), expires_at, attempt_count,
  consumed_at, ip_address, created_at

sessions  (Laravel standard + user_id FK, locale column)
```

**Invariant:** One active unconsumed challenge per user per purpose. Consuming a challenge must
be atomic (single UPDATE with `WHERE consumed_at IS NULL`). Invalidate all prior challenges for
the same user+purpose on new issuance.

### Module 2: Provider Network

```
clinics
  id (ulid), name_encrypted, slug, service_area_code (default: tehran),
  is_active, verified_at, created_at

practitioners
  id (ulid), user_id FK, license_number_encrypted, license_verified_at,
  specialty, display_name, is_active

clinic_memberships
  id (ulid), clinic_id FK, practitioner_id FK, role (reviewer|contact),
  active_from, active_until
```

**Invariant:** `clinic_memberships` does not grant access to patient records. Explicit assignment
or referral grant is always required.

### Module 3: Intake

```
cases
  id (ulid), patient_id FK (users), service_type (opg_review|home_dentistry|referral),
  status (draft|submitted|triaged|in_progress|awaiting_patient|resolved|closed|cancelled),
  intake_locale (fa|ar|en), preferred_contact_locale (fa|ar|en),
  service_area_code, budget_amount_int, budget_currency (IRR), budget_input_unit (toman|irr),
  notes_encrypted, version (optimistic lock integer), created_at, updated_at, submitted_at

case_events
  id (ulid), case_id FK, actor_id FK (users), event_type, from_status, to_status,
  reason_key (translation key), locale, created_at
```

**Invariant:** Status transitions use row-level version checks. Only authorized actors may
trigger each transition (see authorization table below). Record every transition in `case_events`.

Allowed transitions:
- `draft → submitted` (patient, with consent)
- `submitted → triaged` (coordinator)
- `triaged → in_progress` (coordinator)
- `in_progress → awaiting_patient` (coordinator)
- `awaiting_patient → in_progress` (coordinator)
- `in_progress → resolved` (coordinator)
- `resolved → closed` (coordinator or system)
- `* → cancelled` (coordinator, with reason)

### Module 4: Consent

```
policy_versions
  id (ulid), policy_type (privacy|opg_consent|referral_sharing|marketing),
  locale (fa|ar|en), version_string, content_hash (SHA-256 of canonical text),
  content_encrypted, published_at, superseded_at

consent_events
  id (ulid), patient_id FK, policy_version_id FK, purpose, actor_id FK,
  ip_address_hash, created_at
```

**Invariant:** `content_hash` must match the text shown at time of consent.
`policy_versions.content_hash` is immutable after `published_at` is set.
A consent event is only valid for its exact `policy_version_id`.

### Module 5: Documents

```
documents
  id (ulid), case_id FK, uploader_id FK, original_filename_encrypted,
  server_object_name (server-generated, never user-supplied), mime_verified,
  size_bytes, pixel_width, pixel_height, content_hash (SHA-256 of raw bytes),
  status (quarantined|scanning|approved|rejected|scan_failed|deleted),
  version (optimistic lock), upload_consent_event_id FK, created_at, updated_at

scan_attempts
  id (ulid), document_id FK, attempt_number, engine_name, signature_version,
  verdict (clean|malicious|inconclusive), timeout_ms, started_at, completed_at,
  object_hash_at_scan (must equal document.content_hash)

document_access_events
  id (ulid), document_id FK, actor_id FK, access_type (view|download|preview),
  ip_address_hash, created_at
```

**Document lifecycle state machine:**

```
quarantined → scanning → approved  (clean verdict, hash must match)
                       → rejected  (malware or validation failure)
           → scan_failed           (timeout or unavailable scanner)
scan_failed → scanning             (bounded retry, max 3 attempts)
approved → deleted                 (retention action)
rejected → deleted                 (quarantine cleanup)
scan_failed → deleted              (abandoned after max retries)
```

**Critical invariants:**
- `server_object_name` is generated server-side (UUID). Never use the user-supplied filename as
  a storage key.
- `object_hash_at_scan` in `scan_attempts` must equal `documents.content_hash` at approval time.
  Approval must not survive object replacement.
- Promotion (quarantine → approved storage) and database commit must be recoverable if a worker
  crashes between them. Jobs must be idempotent.
- Never expose files on scan failure, stale signatures, or exhausted retries.
- All document responses: `Cache-Control: no-store`. Verify authorization on every single read.

Upload limits (initial, tunable after OPG testing):
- 20 MiB per file
- 3 files per case
- Accepted MIME: `image/jpeg`, `image/png`
- PDF only after its parser and sandbox are separately proven

### Module 6: Coordination

```
assignments
  id (ulid), case_id FK, assignee_id FK (users), role (coordinator|reviewer),
  assigned_at, assigned_by_id FK, unassigned_at

tasks
  id (ulid), case_id FK, assignment_id FK, type, due_at, completed_at, escalated_at

referral_proposals
  id (ulid), case_id FK, coordinator_id FK, clinic_id FK, reason_key,
  proposed_at, status (proposed|accepted_by_patient|declined_by_patient|withdrawn)

referral_grants
  id (ulid), referral_proposal_id FK, patient_consent_event_id FK,
  granted_at, purpose, expires_at, revoked_at
```

**Invariant:** A clinic may only access a case after an active `referral_grants` record exists
for that clinic and that case. Revocation must be immediate for future access; it does not undo
data lawfully received before revocation.

### Module 7: Clinical Review

```
review_revisions
  id (ulid), case_id FK, document_id FK, author_id FK (practitioners),
  content_encrypted, status (draft|published|superseded), locale,
  created_at, published_at

publication_events
  id (ulid), review_revision_id FK, actor_id FK, event_type (published|superseded|flagged),
  created_at
```

**Invariant:** Only a `practitioners` record with an active `clinic_memberships.role = reviewer`
and an `assignments` record for this case may publish a review. Business owner role does not
grant clinical authorization.

### Module 8: Operations

```
outbox
  id (ulid), aggregate_type, aggregate_id, event_type, payload_encrypted,
  recipient_locale, created_at, dispatched_at, dispatched_by_job_id

notification_deliveries
  id (ulid), outbox_id FK, channel (sms), recipient_phone_digest,
  provider_reference, status (pending|sent|failed|delivered), attempt_count,
  last_attempted_at, delivered_at

audit_records
  id (ulid), actor_id FK (nullable for system), action, resource_type,
  resource_id, metadata_json (no PII in metadata), created_at

retention_jobs
  id (ulid), resource_type, resource_id, scheduled_action, scheduled_at,
  executed_at, executed_by_job_id
```

**Invariant:** Dispatch outbox records **after** the wrapping transaction commits (use
Laravel's `afterCommit` dispatch). Outbox records must not contain plaintext phone numbers,
radiograph content, clinical text, or OTP codes. Notification delivery provider callbacks
require HMAC authentication and replay protection.

---

## AUTHENTICATION & AUTHORIZATION

### Patient auth (SMS OTP)

Initial rate limits (implement, tune with real data):
- Challenge expiry: 5 minutes
- Resend cooldown: 1 minute (block new challenge within cooldown)
- Verification attempts per challenge: 5 (lock challenge after 5th failure)
- Phone-level limit: 10 challenges per hour
- IP-level limit: 20 challenges per hour per IP

Never log OTP codes. Store only `bcrypt` verifier in `otp_challenges`.
Non-enumerating responses for all OTP endpoints (`200` even for invalid phone on challenge
creation — do not confirm whether a phone is registered).
Rotate session on successful OTP verification.
Cookies: `Secure`, `HttpOnly`, `SameSite=Lax`. Enforce CSRF on all state-mutation routes.

### Staff auth

Staff (`coordinator`, `clinician`, `owner`, `tech_admin`, `clinic_rep`) require:
- OTP + TOTP MFA (Laravel Fortify or equivalent)
- Session idle timeout: 30 minutes
- Rapid revocation (invalidate all sessions for a user without password)
- Documented recovery process

### Authorization matrix

| Actor | Can access | Cannot access |
|---|---|---|
| `patient` | Own `cases`, own `documents`, own `consent_events`, published `review_revisions` for own case, own `referral_proposals` | Any other patient's records; `audit_records`; internal notes |
| `coordinator` | Assigned `cases`, operational contact details for assigned cases | Clinical publishing; radiograph content unless explicitly required and assigned |
| `clinician` (via `practitioners`) | Assigned review material (`documents` on assigned case) | All other patients; other clinicians' drafts; general patient population |
| `clinic_rep` | Cases with an active `referral_grants` record for their clinic | All other cases; before grant; after revocation |
| `owner` | Network administration, aggregate operational stats | Clinical authorization; individual patient records without assignment |
| `tech_admin` | System operations | Routine clinical browsing; exceptional access requires additional audit trail |

**Default deny.** Check ownership + assignment + clinic membership + document state + sharing
scope on **every request**, not just role. Use Laravel Policies for every model.

For list endpoints: never leak the existence of another patient's record. Return `404` on
`cases/{id}` if the case exists but the requester has no right to it.

---

## HTTP API CONTRACT

Base: `/api/v1`. Language-neutral paths. All requests carry `Accept-Language` or a
`?locale=` allowlist parameter. Responses include `X-Resolved-Locale` header.

| Method | Path | Purpose | Required checks |
|---|---|---|---|
| `POST` | `/auth/otp/challenges` | Start OTP verification | Rate limits (phone + IP); generic response |
| `POST` | `/auth/otp/verifications` | Consume OTP, establish session | Atomic challenge consumption; expiry; attempt count; session rotation |
| `DELETE` | `/auth/sessions/current` | Logout | Auth required; invalidate session and CSRF token |
| `GET` | `/me` | Own profile + preferences | Auth; own user only |
| `PATCH` | `/me/preferences` | Update locale/calendar prefs | Auth; allowlisted fields only; no access-right changes |
| `GET` | `/policies` | Approved localized policy versions | Published only; `?locale=` required |
| `POST` | `/cases` | Create draft case | Auth; verified phone; idempotency key; minimal input |
| `POST` | `/cases/{id}/submit` | Submit draft | Auth; ownership; required consent event; intake feature gate |
| `GET` | `/cases/{id}` | Case status + event history | Auth; ownership or active staff scope |
| `POST` | `/cases/{id}/documents` | Upload to quarantine | Auth; case ownership; active consent; size/type/quota checks |
| `GET` | `/documents/{id}/status` | Scan/processing status | Auth; same ownership/assignment constraints as case |
| `GET` | `/documents/{id}/content` | Download controlled document | Auth; `approved` status; current access grant; audit event; `Cache-Control: no-store` |
| `POST` | `/cases/{id}/referral-decisions` | Accept or decline referral proposal | Auth; patient ownership; exact clinic + scope in body must match proposal |
| `POST` | `/staff/cases/{id}/assignments` | Assign coordinator or reviewer | Staff auth; valid assignee role; version concurrency check |
| `PATCH` | `/staff/cases/{id}/status` | Coordinator status transition | Staff auth; allowed transition for actor role; version check |
| `POST` | `/staff/reviews` | Create review draft | Clinician auth; active assignment on case |
| `PATCH` | `/staff/reviews/{id}` | Edit review draft | Auth; own draft; not yet published |
| `POST` | `/staff/reviews/{id}/publish` | Publish clinician review | Clinician auth; licensed reviewer on assigned case; complete revision |
| `POST` | `/staff/cases/{id}/referral-proposals` | Propose a referral to a clinic | Coordinator auth; assigned; clinic is active |

**Error contract:**
- `401` — unauthenticated
- `403` — authenticated but forbidden (do not disclose record existence for patient-visible resources → use `404`)
- `404` — not found or deliberately nondisclosing
- `409` — state conflict (stale version, duplicate submission)
- `413` — upload too large
- `422` — validation failure (stable `errors` object, translated `message`)
- `429` — rate limited (`Retry-After` header)
- `503` — scanner or dependency unavailable (do not expose raw exception)

Always return a stable `request_id` in error bodies for support tracing.

**Idempotency:** Scope idempotency keys to `actor_id + operation`. Reject same key with
different payload as `409`. Return the original response for replayed identical payloads.

**Intake feature gate:** `INTAKE_ENABLED=false` (env). Apply server-side to all mutation
routes in Intake module. Gate check runs before business logic, not as a UI-only flag.

---

## BACKGROUND WORKERS

All workers use `QUEUE_CONNECTION=database`.

### Queues (separate to prevent head-of-line blocking)

| Queue | Work |
|---|---|
| `otp` | SMS OTP delivery — must never be blocked by scan backlog |
| `scanning` | Document malware scanning |
| `notifications` | Case event notifications to patients and staff |
| `maintenance` | Retention jobs, outbox drain, task escalation |

### Scan job requirements

```
ScanDocumentJob implements ShouldBeUnique (by document_id)
- Transition: quarantined → scanning (record attempt; reject if already scanning/approved)
- Enforce size limit, memory limit, timeout
- Verify file digest against documents.content_hash before scanning
- On clean verdict: promote file (copy to approved storage), then commit DB state in transaction
  If copy succeeds but commit fails: worker restarts and re-runs idempotently (reconcile)
- On malicious or invalid: set rejected; alert operations
- On timeout/unavailable: set scan_failed; schedule bounded retry (max 3)
- After 3 retries: set scan_failed permanently; notify operations; show patient "processing delayed"
- Log engine name + signature version in scan_attempts; never log file content
```

### Outbox job requirements

```
DrainOutboxJob
- Runs every minute via Laravel scheduler
- Processes pending outbox records in batches
- Resolves recipient locale from outbox.recipient_locale (set at enqueue time)
- Dispatches to NotificationDeliveryJob on `notifications` queue
- Marks dispatched_at atomically to prevent double-dispatch

NotificationDeliveryJob
- Sends via SMS provider
- Stores delivery result in notification_deliveries
- Provider callbacks: verify HMAC, check replay via provider_reference uniqueness
- Messages: generic content only, direct patient to authenticated app (no PII in SMS)
```

Worker supervision requirements:
- Workers must restart automatically after host reboot and deploy
- Worker timeout < queue retry interval
- Separate `otp` queue worker process from `scanning` worker process
- Monitor: oldest pending job, failed scan count, scanner signature age, low disk

---

## PHASE-BY-PHASE IMPLEMENTATION INSTRUCTIONS

Work through phases in order. At the end of each phase, run the specified verification.
Do not merge incomplete phase work with the next phase.

---

### PHASE 0 — Project scaffold and host verification checklist

Generate:
1. `composer.json` for Laravel 13.x with PHP `^8.3`. Include: `laravel/framework`, `laravel/sanctum`
   (session-only, no SPA tokens), `propaganistas/laravel-phone` (phone normalization),
   `spatie/laravel-model-states` (state machines). Lock PHP platform constraint.
2. `.env.example` with all required variables (no values): `APP_KEY`, `DB_*`, `QUEUE_CONNECTION`,
   `INTAKE_ENABLED`, `OTP_*`, `SCANNER_*`, `SMS_*`, `ENCRYPT_KEY_*`. Include comments.
3. `app/Modules/` directory structure with `Identity`, `Provider`, `Intake`, `Consent`,
   `Documents`, `Coordination`, `Clinical`, `Operations` subdirectories.
4. A host inspection checklist (Markdown) covering: AlmaLinux + cPanel versions; PHP CLI/FPM/worker
   version consistency; required extensions (`intl`, `mbstring`, `fileinfo`, `pdo_mysql`,
   `openssl`, `bcmath`); private storage directory permissions; outbound SMS provider reachability;
   MariaDB charset (`utf8mb4`) and timezone; cPanel Apache routing configuration (`.htaccess`
   for public/ entrypoint); cron/supervisor capability for background workers.

Verification: `composer validate`, `php artisan config:clear`, `php artisan route:list` returns
no errors on a fresh install.

---

### PHASE 1 — Database migrations

Generate numbered migrations in dependency order. Use InnoDB, `utf8mb4_unicode_ci`.
All migrations must be reversible (`down()` implemented). Include indexes on operational queries.

Migration order:
1. `users` table
2. `otp_challenges` table
3. `clinics` table
4. `practitioners` table
5. `clinic_memberships` table
6. `cases` table + `case_events` table
7. `policy_versions` table + `consent_events` table
8. `documents` table + `scan_attempts` table + `document_access_events` table
9. `assignments` table + `tasks` table
10. `referral_proposals` table + `referral_grants` table
11. `review_revisions` table + `publication_events` table
12. `outbox` table + `notification_deliveries` table + `audit_records` table + `retention_jobs` table
13. Queue jobs table (`php artisan queue:table`) and failed jobs table

Each migration: add foreign key constraints. Add `CHECK` constraints where MariaDB supports them
(e.g., `status IN (…)`, `locale IN ('fa','ar','en')`).

Verification: `php artisan migrate:fresh` on a real MariaDB instance succeeds with zero errors.
Run `php artisan migrate:rollback --step=13` and confirm clean rollback.

---

### PHASE 2 — Encryption, models, and policies

Generate:
1. `EncryptedCast` using Laravel's built-in `Illuminate\Database\Eloquent\Casts\Encrypted`
   for all `*_encrypted` columns. Confirm key rotation path in `.env.example`.
2. `PhoneLookupDigest` cast: HMAC-SHA256 of the canonical E.164 phone using a separate
   `PHONE_LOOKUP_KEY` (not the app key). Store alongside `phone_encrypted`.
3. Eloquent models for all 8 modules, each in its module namespace.
   Include: `$fillable`, correct casts, relationships, and `SoftDeletes` where appropriate.
4. Laravel Policies for: `CasePolicy`, `DocumentPolicy`, `ReviewRevisionPolicy`,
   `ReferralGrantPolicy`. Each method: check ownership + role + active assignment where relevant.
   Default deny (return `false` unless all conditions pass).
5. `FeatureGate::intake()` service (reads `INTAKE_ENABLED` env). Apply as middleware
   `EnsureIntakeEnabled` to all case mutation routes.

Verification: Write a unit test for each Policy's `view` and `update` methods. Test that a
patient cannot access another patient's case (expect `false`). Test that a coordinator without
an assignment cannot access the case (expect `false`).

---

### PHASE 3 — Locale system

Generate:
1. `LocaleServiceProvider`: reads locale from URL prefix (`/fa/`, `/ar/`, `/en/`), falls back to
   `fa`. Calls `App::setLocale()` and `Carbon::setLocale()`. Sets `dir` and `lang` for Blade.
2. Middleware `SetLocaleFromUrl`: extracts locale from route prefix, validates against allowlist
   `['fa', 'ar', 'en']`, stores in `app.locale` and session.
3. Translation file structure: `lang/fa/`, `lang/ar/`, `lang/en/`. Create key files:
   `auth.php`, `case.php`, `consent.php`, `documents.php`, `errors.php`, `notifications.php`.
   Populate with placeholder values that follow the key naming convention (`case.status.submitted`).
4. Blade layout `resources/views/layouts/app.blade.php`:
   - `<html lang="{{ $locale }}" dir="{{ $dir }}">`
   - Self-hosted Inter + Vazirmatn fonts (declare both; CSS applies Vazirmatn when `dir=rtl`)
   - Logical CSS reset: `margin-inline-start` etc.; no `margin-left` in layout CSS
   - Locale switcher that preserves current path across all three locales
5. Number normalization helper `App\Support\DigitNormalizer::toWestern(string $input): string`
   that converts Persian and Arabic-Indic digits to ASCII.
6. `App\Support\MoneyValue` value object: stores `amount_int`, `currency` (`IRR`), `input_unit`
   (`toman|irr`). Conversion: 1 toman = 10 IRR.

Verification: Write tests for `SetLocaleFromUrl` (each prefix sets correct `lang`/`dir`).
Test `DigitNormalizer` with all three digit scripts. Test `MoneyValue` toman↔IRR conversion.
Write a browser test (Laravel Dusk or Pest browser) that loads `/fa/`, `/ar/`, `/en/` and
asserts the `dir` attribute on `<html>`.

---

### PHASE 4 — Authentication

Generate:
1. `POST /auth/otp/challenges` controller:
   - Validate phone (E.164, normalize digits first)
   - Rate limit: phone-bucket (10/hour), IP-bucket (20/hour) via Laravel RateLimiter
   - If phone is in cooldown window (1 minute since last challenge): return `429`
   - Generate 6-digit OTP; bcrypt the verifier; store `otp_challenges` record
   - Dispatch `SendOtpJob` on `otp` queue
   - Always return `HTTP 200` with generic message (do not confirm whether phone exists)
2. `POST /auth/otp/verifications` controller:
   - Find unconsumed, unexpired challenge for submitted phone+purpose
   - Atomic: `UPDATE otp_challenges SET consumed_at = NOW() WHERE id = ? AND consumed_at IS NULL`
   - Verify bcrypt verifier; increment `attempt_count` (reject after 5)
   - On success: create/update `users` record; rotate session; return session cookie
   - On failure: generic error (do not distinguish "wrong code" from "expired")
3. `DELETE /auth/sessions/current`: invalidate session, regenerate CSRF
4. `SendOtpJob`: sends SMS via configured provider. Logs delivery attempt (no OTP code in logs).
   Queue: `otp`.
5. Staff MFA placeholder: middleware `EnsureStaffMfa` that checks a TOTP `mfa_verified_at`
   session key (< 12 hours). Returns `403` with `error.staff.mfa_required` if not set.
   (Full TOTP enrollment deferred to Phase 5.)

Verification: Tests for OTP rate limiting, atomic consumption, replay attack (second
verification with same challenge), expired challenge, 5-attempt lockout, session rotation.
Assert no OTP value appears in any log output.

---

### PHASE 5 — Case intake and consent

Generate:
1. Consent service `ConsentService::requireConsent(User $patient, string $policyType, string $locale): void`
   - Looks up the current published `policy_versions` record for type + locale
   - If none exists: throw `MissingConsentTranslationException` (blocks the flow)
   - Returns the version to show; records event only after patient submission
2. `POST /cases` — create draft:
   - Auth middleware; verified phone required
   - Validate: `service_type` in allowlist, `preferred_contact_locale`, minimal fields only
   - Idempotency: check `X-Idempotency-Key` header; scope to `user_id + key`
   - Create case with `status = draft`; return opaque case ID
3. `POST /cases/{id}/submit`:
   - Ownership check (CasePolicy)
   - Intake feature gate
   - Require active consent event for `privacy` + `service_type`-specific policy in patient's locale
   - If home dentistry: validate service area (Tehran only) — reject with `422` if outside
   - Transition: `draft → submitted` using version check
   - Write `case_events` record; enqueue `CaseSubmittedNotification` to outbox

Verification: Full test for the happy path (draft → submitted). Test consent blocking when
translation is missing. Test that a second submit on the same case returns `409`. Test that
intake gate blocks submission when `INTAKE_ENABLED=false`.

---

### PHASE 6 — Document upload and scanning

Generate:
1. Upload endpoint `POST /cases/{id}/documents`:
   - Auth; CasePolicy; active consent for OPG/document sharing policy
   - Proxy/web-server limit + PHP `upload_max_filesize` + application limit (20 MiB) — all three
   - Validate MIME by inspecting file magic bytes (not extension or `Content-Type` header)
   - Validate pixel dimensions (reject excessive decoded size before full decode)
   - Generate `server_object_name` as UUID (never use original filename as key)
   - Compute SHA-256 of raw bytes; store as `content_hash`
   - Write `documents` record with `status = quarantined`
   - Move file to private quarantine directory (outside `public_html`)
   - Dispatch `ScanDocumentJob` on `scanning` queue
   - Return `HTTP 202` with `document_id` and `status = quarantined`
2. `ScanDocumentJob` (see requirements in Workers section above)
3. `GET /documents/{id}/status`: returns current status; no file content
4. `GET /documents/{id}/content`:
   - Auth; DocumentPolicy (approved status + current access grant)
   - Write `document_access_events` record
   - Stream file with: `Content-Disposition: attachment`, `Content-Type` from verified MIME,
     `Cache-Control: no-store`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: default-src 'none'`
   - No signed S3 URLs (host is cPanel); serve via PHP stream

Verification: Tests for forged extension (JPEG magic, .php extension → reject), oversized file,
pixel bomb attempt, scanner timeout (→ scan_failed), scanner malicious verdict (→ rejected),
clean file (→ approved), idempotent job re-run (reconcile without duplicate), crashed worker
between copy and commit (re-run promotes successfully). Assert no file is served in `scan_failed`
state.

---

### PHASE 7 — Coordination workflows

Generate:
1. `POST /staff/cases/{id}/assignments`: assign coordinator or reviewer
   - Staff auth + EnsureStaffMfa
   - Validate: assignee has correct role; case in assignable state; version check
   - Write `assignments` record; write `case_events`
2. `PATCH /staff/cases/{id}/status`: coordinator status transitions
   - Check transition is in allowed list for coordinator role
   - Version optimistic lock; write `case_events`
3. `POST /staff/cases/{id}/referral-proposals`: propose clinic referral
   - Coordinator auth; active assignment; clinic is active and in service area
4. `POST /cases/{id}/referral-decisions`: patient accepts/declines
   - Patient auth; ownership; proposal must be in `proposed` state
   - If accepted: require `referral_sharing` consent event; create `referral_grants` record
   - Grant: includes exact clinic ID, purpose, expiry; write consent event; write `case_events`
5. Revocation: `DELETE /referral-grants/{id}` — patient can revoke
   - Set `revoked_at`; revocation is immediate for future access checks

Verification: Test that clinic_rep cannot access case before grant. Test that revocation
immediately blocks subsequent `GET /cases/{id}` by clinic_rep. Test double-decision returns `409`.

---

### PHASE 8 — Clinical review

Generate:
1. `POST /staff/reviews`: create review draft
   - Practitioner auth + MFA + active assignment (reviewer role) on case
   - Case must have at least one `approved` document (scan_attempts.verdict = clean)
2. `PATCH /staff/reviews/{id}`: update draft
   - Auth; own draft only; `status = draft` required
3. `POST /staff/reviews/{id}/publish`:
   - Practitioner auth; own draft; active assignment on case; `status = draft`
   - Verify practitioner has current `clinic_memberships.role = reviewer` (active)
   - Transition revision to `published`; supersede prior published revisions for same case
   - Write `publication_events`; enqueue patient notification to outbox
4. `GET /cases/{id}` — patient view: include published review (content_encrypted decrypted for
   display); never include draft revisions in patient response

Verification: Test that owner role cannot publish review. Test that coordinator cannot publish
review. Test that superseded revision is excluded from patient response. Test that draft is not
visible to patient.

---

### PHASE 9 — Notifications and outbox

Generate:
1. `DrainOutboxJob` (scheduler: every minute):
   - Process pending outbox records in batches of 50
   - Atomic claim: `UPDATE outbox SET dispatched_at = NOW() WHERE id IN (?) AND dispatched_at IS NULL`
   - Dispatch `NotificationDeliveryJob` per record on `notifications` queue
2. `NotificationDeliveryJob`:
   - Resolve template by `event_type` and `recipient_locale`
   - Render SMS: generic message only (no PII, no clinical data, no tokens)
   - Send via SMS provider; record result in `notification_deliveries`
   - On provider failure: retry with exponential backoff (max 3); mark `failed` after exhaustion
3. Provider callback endpoint `POST /webhooks/sms/delivery`:
   - Verify provider HMAC signature
   - Check `provider_reference` uniqueness (idempotency)
   - Update `notification_deliveries.status`

Verification: Test that SMS body contains no phone number, clinical content, or OTP.
Test provider callback replay (second identical callback is accepted but produces no state change).
Test outbox drain does not double-dispatch (run job twice; assert one dispatch per record).

---

### PHASE 10 — Audit, retention, and monitoring hooks

Generate:
1. `AuditService::record(string $action, string $resourceType, string $resourceId, array $metadata = [])`:
   - Writes `audit_records`; `$metadata` must not contain PII (enforce via `AuditMetadataSanitizer`)
   - Called automatically via Eloquent observers for: case status changes, document access,
     consent events, assignment changes, review publication
2. Retention job scaffolding: `ScheduleRetentionJob` — creates `retention_jobs` records based
   on configured policies (configurable durations via env, no hardcoded claim of legal compliance)
3. `ExecuteRetentionJob` — executes scheduled retention (soft delete → hard delete after hold period)
4. Monitoring instrumentation (log-based, compatible with cPanel):
   - Log oldest pending queue job age on each scheduler tick
   - Log failed scan count per hour
   - Log scanner signature age (if scanner exposes this)
   - Log disk usage of private storage directory
   - Log failed notification deliveries older than 10 minutes
   - All logs: structured JSON, no PII

Verification: Test AuditMetadataSanitizer strips phone numbers and names from metadata.
Test that document access event is written on every `GET /documents/{id}/content` call.
Test retention job scheduler creates records; test executor marks them complete.

---

### PHASE 11 — Public locale shells and Blade views

Generate:
1. Three locale-prefixed public route groups (`/fa/`, `/ar/`, `/en/`) with `SetLocaleFromUrl`
   middleware. Each group has: home, services/opg, services/home-dentistry, referrals, contact.
2. Blade views for each public page using the locale layout from Phase 3:
   - All copy via translation keys (no hardcoded Persian or Arabic strings)
   - `<link rel="alternate" hreflang="fa" href="...">` etc. on all public pages
   - `<link rel="canonical" href="...">` pointing to the locale's own URL
   - Sitemap route: public pages only (no case or patient pages)
3. Error pages (403, 404, 422, 429, 500, 503) in all three locales with correct `dir`
4. Public pages: `Cache-Control: public, max-age=3600, s-maxage=3600, Vary: Accept-Language`
   Authenticated pages: `Cache-Control: no-store, private`

Verification: Request `/fa/`, `/ar/`, `/en/` — each returns `lang` and `dir` in `<html>`.
Request a non-existent locale prefix → redirects to `/fa/`. Run HTML validator on all three
home pages. Confirm no private cache headers on public responses and vice versa.

---

### PHASE 12 — Test matrix and release checklist

Generate a comprehensive Pest test suite covering (minimum, not exhaustive):

**Auth:**
- Wrong OTP → `422`; expired challenge → `422`; replayed consumed challenge → `422`
- Concurrent verifications on same challenge (race condition) → only one succeeds
- Resend within cooldown → `429`; after cooldown → `200`
- Staff endpoint without MFA session flag → `403`

**Authorization:**
- `GET /cases/{id}` by another patient → `404`
- `GET /cases/{id}` by coordinator without assignment → `403`
- `GET /documents/{id}/content` by clinic_rep without grant → `404`
- `POST /staff/reviews/{id}/publish` by coordinator role → `403`
- `POST /staff/reviews/{id}/publish` by owner role → `403`

**Upload:**
- JPEG with `.php` extension → `422`
- PNG file exceeding 20 MiB → `413`
- MIME declared as `image/jpeg` but magic bytes are PDF → `422`

**Scanner:**
- Clean file → `approved`; benign file served on content endpoint
- EICAR test string → `rejected`; content endpoint returns `403`
- Scanner timeout → `scan_failed`; content endpoint returns `503`
- After 3 retries exhausted → permanent `scan_failed`; content endpoint returns `503`
- Idempotent job re-run → no duplicate scan_attempts record; no duplicate storage copy

**Workflows:**
- Repeated `POST /cases/{id}/submit` → second call returns `409`
- Status transition not in allowed list → `409`
- Stale version on concurrent updates → `409`
- Referral decision after proposal withdrawn → `422`

**Locale:**
- `/fa/` page: `<html lang="fa" dir="rtl">`
- `/ar/` page: `<html lang="ar" dir="rtl">`
- `/en/` page: `<html lang="en" dir="ltr">`
- Consent endpoint with missing Arabic translation → `503` with `error.consent.translation_unavailable`
- Notification delivery: SMS body contains no PII; uses recipient's stored locale, not worker locale

**Document access:**
- Every `GET /documents/{id}/content` writes `document_access_events`
- Response headers: `Cache-Control: no-store`, `X-Content-Type-Options: nosniff`

Generate also:
- A deployment checklist (Markdown): locked `composer.lock`; no `.env` in repo; no debug mode in
  prod; private storage not accessible via web; Apache/cPanel routing tested; worker supervisor
  configured; backup encryption verified; `INTAKE_ENABLED=false` confirmed before go-live
- A rollback procedure: what to do if a migration fails in production (expand/contract pattern,
  no blind rollback that drops a column already used by live records)

---

## WHAT NOT TO GENERATE (ever)

- Payment, billing, or invoice code
- Marketplace, bidding, or comparative clinic-ranking algorithm
- AI/LLM diagnosis or radiograph interpretation
- DICOM file handling
- Guardian/minor/dependent patient flows
- WordPress, WooCommerce, or any CMS database dependency
- An Nginx config (host is Apache/cPanel)
- An always-running Node.js process
- Hardcoded legal retention durations or compliance claims
- Any code that logs: OTP codes, phone numbers, patient names, clinical text, encryption keys
- Any code that reads an uploaded filename or browser MIME type as a trusted value
- Bearer token download URLs for documents (must be session-auth + server-stream)
- A "best clinic" recommendation algorithm (record coordinator reasoning only)
- Any code that sends clinical content, patient names, or tokens in SMS notifications
- A signed storage URL that bypasses server-side authorization

---

## START COMMAND

Begin with **PHASE 0**. Show the complete generated files. After I confirm Phase 0,
I will say "proceed to Phase 1" and you will continue in order.

For each phase: generate complete, working code (not pseudocode or stubs). Add inline
comments where the architecture document imposes a specific invariant.
Flag any ambiguity that would materially affect implementation before generating — one
clarifying question maximum per phase.
