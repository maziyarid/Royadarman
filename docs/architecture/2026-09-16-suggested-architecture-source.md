# Royadarman Suggested Architecture — Source Reconciliation

This file records the architecture proposal supplied on 2026-09-16 in `Royadarman Suggested Architecture.docx`, normalized for repository use.

It is **source/provenance**, not the final implementation authority. The final reconciled decisions are in `2026-09-16-canonical-architecture.md`.

## Product position

Royadarman is a **Persian-first dental access, discovery, referral, coordination, and CRM platform for Tehran**. Its public model covers guidance, clinic referral, home-dentistry coordination, preliminary OPG review, communication, follow-up, and operational CRM. Royadarman itself must not be presented as the diagnosing/treating clinician.

Maintain four boundaries:
- **Coordination:** intake, search, scheduling requests, referral, dispatch, communication, follow-up.
- **Clinical care:** examination, diagnosis, treatment planning, image interpretation, prescription, clinical escalation by licensed clinicians.
- **Practice operations:** branch capacity, schedules, staff workflows, service records, future financial operations if introduced.
- **Growth CRM:** inquiry management, attribution, recall, reactivation, treatment-plan follow-up.

Every clinical conclusion must be attributable to a licensed clinician. OPG review is preliminary and does not replace in-person examination.

## Architecture direction in the source document

The document first describes a generic modular-monolith target and mentions a possible typed backend, PostgreSQL/PostGIS, Redis, private object storage, and Flutter/React Native.

Later in the same document, after direct repository/VPS inspection, it explicitly **replaces that earlier direction** and states that the real system is already:
- Laravel 13 on PHP 8.3;
- MariaDB/MySQL-family via Eloquent;
- Blade/Vite/Tailwind frontend assets under `backend/`;
- OTP + staff MFA;
- relational integrity in migrations;
- encrypted casts for sensitive fields;
- a fail-closed OPG document pipeline;
- production preflight and release traceability.

The source therefore concludes that the correct architectural direction is:

> **Harden and formalize the existing Laravel modular monolith; do not rewrite the platform into Node/Nest/Fastify/PostgreSQL/PostGIS.**

## Canonical repository structure proposed by the source

- `backend/` becomes the only deployable application.
- `Current Public_HTML/` and `Front-end v1/` are historical/non-canonical and should be archived or generated from the canonical app rather than maintained in parallel.
- Add version-controlled infrastructure under `infra/`.
- Add architecture, audit, security, and incident-response documentation under `docs/`.
- CI must build/test the real `backend/resources` frontend.

## Core product/domain recommendations

### Identity
- OTP login and staff MFA.
- Session inventory and session revocation.
- Automatic session revocation after sensitive account/security changes.
- Separate future native-mobile guard using Laravel Sanctum rather than replacing web session/CSRF semantics.

### Clinic/provider network
- organizations, branches, clinicians, specialties, equipment, service areas, verification freshness, capacity and availability.

### Discovery
- ask what the patient needs before asking which clinic they know;
- service/suitability first, distance second;
- neighborhood, approximate distance, availability/response window, verified specialty/equipment, home-visit coverage and freshness;
- transparent explanation of why a branch is suitable.

### Geospatial recommendation
The repository-grounded section explicitly recommends **no PostGIS**. Use plain latitude/longitude fields, indexed bounding-box filtering, and Haversine distance unless a future measured need justifies a database change.

### Referral/appointment
- explicit states;
- append-only referral events;
- SLA timers;
- clinic accept/decline/clarification;
- coordinator overrides with reason;
- idempotent transactional appointment request/hold/confirmation logic.

### OPG
- preserve the existing quarantine → hash verification → ClamAV scan → re-hash → approve/release model;
- add assignment, structured clinician review, sign-off, patient-safe summary, urgency/escalation and operational monitoring;
- no autonomous diagnosis in the first release.

### Home dentistry
- distinct Tehran-only eligibility/dispatch workflow;
- coverage validation;
- capability/equipment matching;
- exact-address protection;
- explicit urgent/hospital escalation.

### CRM
Pipelines for inquiry, referral, OPG, home visit, recall/reactivation, treatment-plan follow-up and abandoned requests. Each task should have an owner, next action, channel, status/outcome and reason for loss.

## Persian-first UX requirements from the source

The source explicitly asks for an experience resembling the simplicity of local mobility apps:
- prominent “What do you need?” entry point;
- manual neighborhood selection if location permission is denied;
- plain-Persian service cards;
- map/list results when data exists;
- explanation of suitability;
- step-by-step request/referral progress;
- one-tap coordinator contact through approved channels;
- progressive disclosure instead of long forms;
- large touch targets, strong contrast and readable typography;
- clear routine vs urgent distinction;
- Persian typography/numerals, RTL, Jalali-aware presentation, Tehran time, local phone/address handling.

## Security and staging recommendations from the source

- unique accounts and least privilege;
- MFA for staff/admin roles;
- branch/tenant authorization server-side;
- private sensitive storage;
- malware/content validation;
- audit trails;
- session/device management;
- tested encrypted backups and restoration;
- separate development/test/staging/production credentials, DBs, storage and keys;
- staging uses only synthetic/de-identified data.

## Highest-priority gap identified in the source audit

The CMS media path currently accepts formats including SVG and derives storage behavior from uploaded file metadata. The source identifies this as the most significant public content-security gap.

Recommended remediation:
- reject SVG by default;
- accept only safe raster formats such as JPEG/PNG/WebP;
- determine type from bytes;
- decode/re-encode images to strip metadata/polyglot payloads;
- random server filenames;
- size/dimension limits;
- regression tests for spoofed extensions and malformed content.

## CI/release findings in the source

- `composer audit --locked` is currently informational because CI uses `|| true`;
- the frontend CI job checks the obsolete `Front-end v1` rather than the deployed frontend;
- branch protection/status checks need strengthening;
- the repo needs a committed frontend lockfile;
- add MariaDB migration CI in addition to SQLite;
- add browser/E2E coverage;
- move worker/scheduler/ClamAV/deploy/rollback configuration into version control.

## Phased direction from the source

1. release governance and canonical frontend CI;
2. close CMS media security gap and remove parallel frontend ambiguity;
3. session inventory/revocation;
4. infrastructure as code + MariaDB migration CI;
5. browser/E2E/API-security test depth;
6. mobile/discovery expansion;
7. line-by-line follow-up audit of CMS controllers/validators, webhooks, seed data, rollback paths and frontend DOM sinks.

## Interpretation rule

Any schema snippets or technology examples in the source are illustrative. Before implementation, reconcile every change against the actual Laravel migrations, controllers, policies, jobs and tests.
