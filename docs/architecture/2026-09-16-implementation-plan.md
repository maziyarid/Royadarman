# Royadarman Implementation Plan — Architecture, Presentation Rescue, and Security Hardening

**Base:** `main` / deployed release `2f9763a3a80e7870dbd6aca91b8a570b89426cb2`

This plan converts the reconciled architecture into execution work. Every implementation must begin by auditing current files/migrations/controllers/tests and must preserve working security controls unless a tested replacement is demonstrably safer.

## Workstream 0 — Repository hygiene and source of truth

- Declare `backend/` canonical in root README and architecture docs.
- Archive/deprecate `Front-end v1/` and `Current Public_HTML/`; do not delete without provenance.
- Add `backend/package-lock.json` generated from the current package manifest.
- Make Vite/Tailwind build reproducible via `npm ci && npm run build`.
- Add architecture index linking source proposal, canonical architecture, implementation plan, security docs, and build prompt.

**Acceptance:** a new developer can clone the repo, run one documented backend/frontend build path, and identify exactly which tree deploys.

## Workstream 1 — Presentation rescue / staging UI

### Public site
- Redesign Persian homepage around one service-first CTA: `چه خدمتی نیاز دارید؟`
- Use logo blue `#2947A3`, orange `#F2A566`, warm off-white `#F7F5EF` consistently.
- Introduce high-quality licensed/generated imagery; no random placeholder images.
- Add service cards for OPG, referral, and Tehran home dentistry.
- Add “how it works”, trust/safety, location coverage, FAQ, and contact/support sections.
- Improve header/mobile navigation and sticky primary action.

### Patient guided flow
Implement a progressive flow rather than one long form:
1. service selection;
2. urgency/routine distinction;
3. Tehran area/neighborhood selection;
4. optional location/geolocation input with manual fallback;
5. preferences/time/budget/accessibility;
6. OPG/document upload when relevant;
7. consent summary with expandable full text;
8. review/submit;
9. success screen with request code and next-step timeline.

### Location UX
- Searchable Tehran neighborhood selector.
- `Use my location` only with user action and graceful denial fallback.
- Map-like or real map/list result view when data/adapters exist.
- Never fake live provider availability or travel time.

### Dashboards
Refactor six current role dashboards into task-oriented UX:
- patient: active request timeline, documents, support, new request;
- coordinator: priority queue, SLA timers, referral/home-visit actions;
- clinician: assigned reviews, document context, review/sign-off state;
- clinic representative: incoming referrals, response deadlines, capacity context;
- owner: aggregate operations/partner performance/CMS, no implicit clinical access;
- technical admin: release, queue, scanner, failed jobs, integrations, feature flags.

### Staging presentation portal
- Permanent `/pres/` landing page in Persian.
- Persistent page, but role handoffs remain safely generated rather than permanent production bearer credentials.
- Staging-only deterministic patient OTP.
- Visible staging banner.
- Reset/reseed action available to operators, not anonymous visitors.
- No real external side effects.

**Acceptance:** presentation can be completed end-to-end on mobile and desktop without encountering unfinished copy, broken routes, raw debug/admin layouts, or real external dependencies.

## Workstream 2 — CMS media security (P0)

- Reject SVG and GIF in the primary CMS upload path unless explicitly re-approved.
- Detect actual MIME from bytes.
- Decode/re-encode JPEG/PNG/WebP to a safe normalized format.
- Random server-side filenames; never trust original extension.
- Strip metadata.
- Enforce pixel/size limits.
- Add spoofed extension/polyglot tests.
- Serve through controlled endpoint or hardened media origin.

**Acceptance:** SVG/script upload, extension spoofing, and malformed raster uploads fail closed; valid raster media is re-encoded and served safely.

## Workstream 3 — CI/CD and release governance

- Rewrite frontend CI to build `backend/` assets.
- Add `backend/package-lock.json` and `npm ci`.
- Make Composer audit blocking.
- Add npm high-severity audit.
- Add MariaDB migration/test matrix.
- Add browser/E2E suite.
- Add API session/CSRF/webhook signature tests.
- Protect `main` with required checks/review.
- Add dependency automation.
- Add release manifest and deterministic deploy/rollback scripts.

**Acceptance:** no direct unreviewed production release; failed audit/test/build/preflight blocks deployment.

## Workstream 4 — Identity and session hardening

- Add session inventory to profile/admin UI.
- Revoke current/other/all sessions.
- Automatically revoke after phone/TOTP/recovery-code/privilege changes.
- Add audited privileged compromise-response action.
- Add runbook and tests.

## Workstream 5 — Clinic network and discovery

- Formalize organization/branch/service/specialty/equipment/verification models based on current migrations; additive migrations only.
- Add latitude/longitude and service radius where absent.
- Search: suitability filter first, then Haversine distance/availability/preferences.
- Map/list results explain recommendation reasons.
- Add freshness timestamps and re-attestation workflow.

## Workstream 6 — Referral/appointment/home dentistry

- Append-only referral event trail and SLA timer.
- Clinic accept/decline/clarification.
- Coordinator override with reason.
- Appointment request/hold/confirmation with idempotency and source-of-truth rules.
- Home-dentistry Tehran coverage, exact-address protection, eligibility, capability/equipment matching, dispatch, escalation.

## Workstream 7 — OPG clinical workflow enhancement

Preserve the scanner/quarantine pipeline. Add:
- workload-based clinician assignment;
- structured review form;
- limitations + urgency + next-step fields;
- immutable/versioned sign-off;
- safe patient summary;
- overdue/urgent queue;
- health monitoring with clean/EICAR synthetic fixtures.

No autonomous diagnosis.

## Workstream 8 — CRM and integrations

- inquiry pipeline;
- referral pipeline;
- OPG pipeline;
- home-visit pipeline;
- recall/reactivation;
- treatment-plan follow-up;
- abandoned request recovery.

Each task: owner, due/next-action, channel, template version, delivery status, outcome/loss reason.

Integration adapters must have retries, idempotency, health checks, dead-letter/replay, and documented system-of-record ownership.

## Definition of done for every workstream

- Persian-first UI/copy reviewed.
- Authorization policy + positive/negative tests.
- No new production side effect without explicit configuration.
- SQLite test suite and MariaDB migration path pass where relevant.
- Pint passes.
- Composer/NPM security gates pass or have documented temporary exception.
- Browser smoke/E2E for visible user journey.
- Documentation updated.
- Rollback path documented.
- No change is deployed directly to production before staging verification.
