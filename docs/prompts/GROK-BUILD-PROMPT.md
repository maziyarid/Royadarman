# Grok Build Prompt — Royadarman Final Architecture + Presentation Rescue

You are taking over implementation of **Royadarman** from the canonical GitHub repository:

`https://github.com/maziyarid/Royadarman`

## Mission

Turn the existing Laravel application into a polished, Persian-first, presentation-ready and production-hardened dental access/referral/coordination platform **without rewriting the proven backend stack**.

Your implementation authority, in order, is:

1. `docs/architecture/2026-09-16-canonical-architecture.md`
2. `docs/architecture/2026-09-16-implementation-plan.md`
3. `backend/ARCHITECTURE.md`
4. the real current migrations/controllers/policies/tests in `backend/`
5. `docs/architecture/2026-09-16-suggested-architecture-source.md` for provenance/context only

If documents conflict, prefer the higher-ranked authority and verified repository behavior. Never treat illustrative schema/code in the source proposal as literal current database structure until you reconcile it against migrations.

## Non-negotiable stack decisions

- Keep **Laravel 13 / PHP 8.3 / MariaDB/MySQL-family**.
- Keep the **modular monolith**.
- `backend/` is the **only canonical deployable application**.
- Do **not** rebuild in Node/Nest/Fastify.
- Do **not** migrate to PostgreSQL/PostGIS.
- Do **not** create microservices without a measured reason.
- Preserve the existing fail-closed clinical-document upload/ClamAV/authorization pipeline.
- Preserve OTP + staff MFA security; do not weaken production auth for demos.
- OPG review is preliminary and clinician-attributable; do not add autonomous diagnosis.

## Immediate priority: presentation rescue

The current visible experience is too simple and incomplete. Treat the first implementation lane as a professional product redesign, not a cosmetic patch.

### Design direction

Think **Uber/Snapp clarity applied to healthcare coordination**:
- one obvious next action;
- progressive disclosure;
- mobile-first;
- large touch targets;
- polished empty/loading/error/success states;
- minimal cognitive load;
- Persian copy written natively, not mechanically translated.

Use the existing logo palette as mandatory tokens:
- primary blue `#2947A3`
- deep blue `#162B70`
- accent orange `#F2A566`
- warm off-white `#F7F5EF`

Do not invent a different primary palette.

### Visual assets

Replace weak/placeholding imagery with either:
- properly licensed stock photography, with source/license recorded; or
- original generated imagery created for Royadarman.

Images must not falsely imply a stock person is a Royadarman clinician/patient. Optimize to responsive WebP/AVIF where practical and add good Persian alt text.

### Public homepage

Redesign the Persian homepage around:
- a premium hero;
- `چه خدمتی نیاز دارید؟` as the main entry;
- clear CTAs;
- service cards: OPG preliminary review, referral/coordination, Tehran home dentistry;
- Tehran coverage/location affordance;
- how-it-works;
- trust/safety boundary;
- FAQ;
- support/contact;
- polished footer/header/mobile navigation.

### Guided patient flow

Replace the current long-form feel with steps:
1. choose service;
2. routine vs urgent;
3. select/search Tehran area/neighborhood;
4. optional location permission with manual fallback;
5. preferences/time/budget/accessibility;
6. OPG/document upload only when relevant;
7. readable consent summary + expandable full policy;
8. review and submit;
9. success screen with request reference + next-step timeline.

Do not fake live clinic availability, price, routing, or travel time. If adapters/data are not ready, label the state honestly and provide coordinator fallback.

### Discovery UX

Implement service/suitability-first discovery. Distance is secondary.
Use existing/added plain latitude/longitude columns + bounding-box/Haversine logic; no PostGIS.
When data exists, show map/list results with:
- branch neighborhood;
- approximate distance;
- response/availability window;
- verified specialty/service/equipment;
- home-visit coverage;
- verification freshness;
- concise reasons for suitability.

### Dashboards

Redesign all six existing role dashboards as task-oriented workspaces:
- Patient
- Coordinator
- Clinician
- Clinic representative
- Owner/business admin
- Technical admin

Preserve server-side authorization. Do not reveal clinical content to owner/technical roles merely because they are privileged operational users.

## Staging requirements

`staging.royadarman.com` must remain a separate synthetic QA/presentation environment:
- separate DB/secrets/storage;
- Persian default;
- `noindex,nofollow,noarchive`;
- valid TLS;
- no real SMS/email/payment/clinic side effects;
- deterministic staging OTP for patient testing;
- permanent Persian `/pres/` portal;
- role shortcuts may mint safe temporary handoffs internally, but do not create permanent production bearer URLs;
- visible `محیط آزمایشی` banner;
- operator reset/reseed command;
- all six role entries smoke-tested.

Production remains conservative until operational gates are satisfied.

## Security work that must happen before broad CMS usage

Current CMS media handling is a P0 issue.
Implement a `CmsMediaSanitizer` or equivalent:
- reject SVG by default;
- restrict to JPEG/PNG/WebP;
- inspect real MIME/content;
- re-encode image pixels to safe WebP/AVIF;
- strip metadata;
- random server filenames;
- dimension/size limits;
- spoofed-extension/polyglot regression tests;
- controlled serving policy.

Do not rewrite the existing clinical-document pipeline. Monitor and test it instead.

## Repository/CI work

- Generate and commit `backend/package-lock.json`.
- Make `npm ci && npm run build` the reproducible frontend path.
- Rewrite CI so it builds `backend/resources` rather than `Front-end v1`.
- Make `composer audit --locked` blocking.
- Add `npm audit --audit-level=high`.
- Add MariaDB migration/test job.
- Add browser E2E (Playwright or Dusk) for primary journeys and role isolation.
- Add explicit session/CSRF/webhook-signature tests.
- Add Dependabot/Renovate.
- Add `infra/` with queue/scheduler/ClamAV/deploy/rollback config.
- Add release manifest capturing SHA, frontend asset hash, migration status, and preflight result.
- Protect `main` with reviewed PRs and required successful checks. Never force-push production history.

## Identity/session improvements

Use Laravel database sessions as the resource; do not invent a parallel session system.
Add:
- active session list;
- revoke one / revoke others / revoke all;
- automatic revocation after sensitive account changes;
- audited admin compromise response;
- runbook and tests.

## Product expansion after presentation/security baseline

Then implement incrementally:
1. verified clinic/branch/service/specialty/equipment records;
2. Tehran suitability discovery;
3. referral event lifecycle and SLAs;
4. appointment requests/holds/confirmation only with defined source-of-truth;
5. home-dentistry eligibility/dispatch/escalation;
6. clinician OPG assignment/review/sign-off UX;
7. CRM pipelines and integrations;
8. optional Sanctum `/api/v1/mobile/*` guard for future native apps while preserving existing web session/CSRF APIs.

## Implementation discipline

Before changing a domain:
1. inspect current migrations, models, controllers, policies, jobs, views, config, and tests;
2. state what exists and what is missing;
3. make additive changes rather than replacing working controls;
4. add positive and negative authorization tests;
5. run formatter/tests/build/audits;
6. verify staging in a real browser-sized flow;
7. update docs.

Do not leave TODO placeholders, dead buttons, fake metrics, lorem ipsum, broken navigation, or presentation-only screens disconnected from the real app.

## Quality gate before calling a phase complete

Run, at minimum:
- `composer validate --strict`
- `composer audit --locked`
- `vendor/bin/pint --test`
- full PHPUnit suite
- `php artisan route:cache` and cached route boot
- `php artisan config:cache` and cached boot
- clean migration on SQLite tests
- MariaDB migration/test job for CI
- `npm ci`
- `npm audit --audit-level=high`
- `npm run build`
- browser/E2E smoke for Persian public site, patient flow, all six role dashboards, RTL/LTR parity, staging noindex, and auth boundaries
- `php artisan royadarman:preflight` with the correct environment profile

Never declare success merely because pages render.

## Git workflow

Create a dedicated branch from current `main`.
Use small, reviewable commits grouped by workstream.
Do not deploy directly to production during the build.
Use staging for iterative UI/UX review.
Provide a final report containing:
- commits;
- migrations;
- routes added/changed;
- tests added;
- screenshots or browser QA notes for primary views;
- dependency audit result;
- known remaining external/operational gates;
- rollback instructions.

## First execution order

1. Audit current repo against the two canonical architecture docs.
2. Fix canonical frontend build reproducibility (`package-lock`, real Vite build, CI path).
3. Execute the presentation rescue on staging: homepage, guided patient flow, `/pres/`, all dashboards, visual assets, responsive/mobile QA.
4. Close CMS media security gap.
5. Add browser/E2E tests and MariaDB CI.
6. Add session inventory/revocation and infrastructure-as-code.
7. Continue discovery/referral/OPG/home-dentistry/CRM expansion in prioritized slices.

Start implementation immediately after producing a concise gap table. Do not redesign the architecture again unless repository evidence contradicts the canonical documents.
