# Royadarman delivery checklist

Updated: 2026-09-08

## Architecture and product

- [x] Represent Royadarman as a 24/7 guidance, coordination, and support centre—not a doctor or single clinic.
- [x] Keep home dentistry explicitly limited to Tehran.
- [x] Describe OPG review as preliminary and performed only by licensed dentists.
- [x] Choose Laravel 13/PHP 8.3/MariaDB 10.11 on the existing AlmaLinux/cPanel/Apache host.
- [x] Exclude WordPress from identity, cases, clinical documents, consent, and referrals.
- [x] Document trust boundaries, roles, state transitions, idempotency, retention, and rollback.

## Brand and multilingual frontend

- [x] Create an original lapis/apricot/mineral identity and runtime token system.
- [x] Create the tooth/support-arcs logo, favicon, and original service-icon family.
- [x] Server-render Persian (`fa`, default RTL), Arabic (`ar`, RTL), and English (`en`, LTR).
- [x] Add locale routes, switcher, localized metadata, canonical and hreflang links.
- [x] Add semantic navigation, skip link, visible focus, reduced motion, and responsive breakpoints.
- [x] Keep intake state honest and visibly unavailable until operational activation.

## Backend

- [x] Patient OTP and staff OTP + TOTP/recovery authentication.
- [x] Role/policy isolation; owner has no implicit patient or clinical access.
- [x] Versioned localized consent with fail-closed missing translations.
- [x] Idempotent case draft/submit workflow and explicit state machine.
- [x] OPG JPEG/PNG validation, quarantine, hashing, scan attempts, promotion, and audited streaming.
- [x] Licensed assigned-clinician publishing and append-only review revisions.
- [x] Referral proposal, patient decision, and minimum-data referral grants.
- [x] Transactional outbox, locale-aware delivery, signed callbacks, and retention jobs.
- [x] Stable API error codes and per-request ULID correlation IDs.

## Production platform

- [x] Dedicated least-privilege MariaDB database/user and clean migrations.
- [x] Production environment, debug off, secure/HTTP-only/SameSite=Lax sessions.
- [x] Independent phone-hash and callback secrets.
- [x] ClamAV installed, current, enabled, and clean-file smoke-tested.
- [x] Database queue worker supervised by systemd; scheduler runs every minute.
- [x] Public root contains only the Laravel front controller, public assets, and preserved MCP directory.
- [x] PHP 8.3 assigned to the Royadarman vhost through WHM.
- [x] Pre-switch public-root, source, and database backups created with SHA-256 records.
- [x] Intake remains server-side disabled.

## Verification

- [x] Pint passes on 95 PHP files.
- [x] 25 tests / 81 assertions pass.
- [x] Strict Frontend Design Premium audit: zero findings.
- [x] Live `/fa/`, `/ar/`, `/en/`, `/up`, assets, redirects, and protected-path checks pass.
- [x] Live unauthenticated and validation API envelopes include stable codes and request IDs.
- [x] Browser verified all locales, direction, language switching, FAQ behavior, keyboard skip-link focus, loaded SVG assets, and no desktop horizontal overflow.
- [x] Queue and signature updater active; no failed jobs.

## Re-verification 2026-09-10 (clean checkout, PHP 8.4.24, SQLite in-memory)

- [x] `php artisan test` — 25 tests / 81 assertions pass.
- [x] `vendor/bin/pint --test` — 95 files clean (Pint auto-fixed 78 files with trailing-newline/brace drift).
- [x] `php artisan migrate --force` — 7 migrations apply cleanly.
- [x] `php artisan route:list` — 22 routes.
- [x] Live HTTP smoke (`php artisan serve`): `/` 302→`/fa/`, `/up` 200, `/fa|ar|en/` 200 with correct `lang`/`dir`, `/fr/` 404, all `/assets/*` 200.
- [x] API error envelopes carry stable codes + `request_id`; `/api/v1/policies/consent` returns `error.consent.translation_unavailable` (fail-closed).
- [x] `lang/{fa,ar,en}/ui.php` — 32 keys each, zero missing cross-locale.
- [x] `Front-end v1/v3-preview` — all assets 200, `node --check app.js` OK, jsdom execution 0 runtime errors.
- [x] Added `.env.example` (repo previously shipped without one).
- [x] Fixed `public/index.php` hardcoded `/home/royadarman/apps/royadarman-backend` path → `dirname(__DIR__)` so the app boots in any environment. The production snapshot `Current Public_HTML/index.php` intentionally keeps the hardcoded path.
- [x] Removed 15 timestamped `.bak.*` editor backup files and one unreferenced empty `opg-hero.jpg` from `Front-end v1/v3-preview/`.

## Activation gates still intentionally open

- [ ] Approve the exact legal consent/privacy text in all three languages.
- [ ] Configure the production SMS provider credentials/templates and test delivery/callbacks.
- [ ] Approve a document-retention period and encrypted backup/restore procedure.
- [ ] Confirm named coordinator coverage and licensed clinical lead/credential records.
- [ ] Run EICAR and forced-timeout scanner drills in an operator-approved maintenance window.
- [ ] After the above, seed the approved policies/providers, run an end-to-end case rehearsal, then set `INTAKE_ENABLED=true`.
