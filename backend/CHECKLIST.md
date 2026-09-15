# Royadarman delivery checklist

Updated: 2026-09-11

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
- [x] CLI, cron, and queue commands use `/usr/local/bin/ea-php83` explicitly; `royadarman:preflight` guards against unsafe production config.
- [x] Pre-switch public-root, source, and database backups created with SHA-256 records.
- [x] Intake remains server-side disabled.

## Verification

- [x] Pint passes on 117 PHP files.
- [x] Tests pass with an explicit PHP >= 8.3 binary (clean checkout, SQLite in-memory).
- [x] Strict Frontend Design Premium audit: zero findings.
- [x] Live `/fa/`, `/ar/`, `/en/`, `/up`, assets, redirects, and protected-path checks pass.
- [x] Live unauthenticated and validation API envelopes include stable codes and request IDs.
- [x] Browser verified all locales, direction, language switching, FAQ behavior, keyboard skip-link focus, loaded SVG assets, and no desktop horizontal overflow.
- [x] Queue and signature updater active; no failed jobs.

## Re-verification 2026-09-11 (clean checkout, PHP 8.4.24, SQLite in-memory)

All commands use an explicit PHP >= 8.3 binary (`"$PHP" artisan ...`). These are
clean-checkout checks, not live-production checks (see DEPLOYMENT.md for the
distinction).

- [x] `"$PHP" artisan test` — 172 tests, 439 assertions, 0 failures.
- [x] `vendor/bin/pint --test` — 117 files, 0 issues.
- [x] `"$PHP" artisan migrate:fresh --force` — 14 migrations apply cleanly.
- [x] `"$PHP" artisan route:list` — 24 routes (including patient consent accept/revoke).
- [x] `"$PHP" artisan route:cache` then `route:clear` — cached route boot verified.
- [x] `"$PHP" artisan config:cache` then `config:clear` — config cache verified.
- [x] `"$PHP" artisan royadarman:preflight` — fails closed on missing/empty/too-short phone-hash key and on key==APP_KEY; passes with a valid independent key.
- [x] `composer audit --locked` — no advisories.
- [x] Live HTTP smoke (`"$PHP" artisan serve`): `/` 302→`/fa/`, `/up` 200, `/fa|ar|en/` 200 with correct `lang`/`dir`, `/fr/` 404, all `/assets/*` 200.
- [x] API error envelopes carry stable codes + top-level `request_id`; domain codes (`assignment.*`, `review.*`) returned in `error.code`.
- [x] `lang/{fa,ar,en}/ui.php` — 32 keys each, zero missing cross-locale.
- [x] `Front-end v1/v3-preview` — all assets 200, `node --check app.js` OK, jsdom execution 0 runtime errors.
- [x] Added `.env.example` (repo previously shipped without one).
- [x] Fixed `public/index.php` hardcoded `/home/royadarman/apps/royadarman-backend` path → `dirname(__DIR__)` so the app boots in any environment. The production snapshot `Current Public_HTML/index.php` intentionally keeps the hardcoded path.
- [x] Removed 15 timestamped `.bak.*` editor backup files and one unreferenced empty `opg-hero.jpg` from `Front-end v1/v3-preview/`.
- [x] `INTAKE_ENABLED=false` unchanged.

## Activation gates still intentionally open

- [ ] Approve the exact legal consent/privacy text in all three languages.
- [ ] Configure the production SMS provider credentials/templates and test delivery/callbacks.
- [ ] Approve a document-retention period and encrypted backup/restore procedure.
- [ ] Confirm named coordinator coverage and licensed clinical lead/credential records.
- [ ] Run EICAR and forced-timeout scanner drills in an operator-approved maintenance window.
- [ ] After the above, seed the approved policies/providers, run an end-to-end case rehearsal, then set `INTAKE_ENABLED=true`.

## Panel demo (unreleased branch, 2026-09-14)

- [x] Fixed six-role TEST identity registry and signed demo access.
- [x] Demo sessions restricted to read-only role panels (and TEST-DEMO case pages).
- [x] **P1** Immutable `clinics.synthetic_demo_key`; seeder fails closed on a reserved-name collision with a live clinic.
- [x] Idempotent `demo.panel.seeded` audit row.
- [x] phpunit forces `APP_URL=http://localhost` and never writes production `release-identity.json`.
- [ ] Merge PR #8 to `main` after the regression suite is green on PHP >= 8.3.
- [ ] On deploy, regenerate `storage/app/release-identity.json` with `royadarman:release-identity --write`.
