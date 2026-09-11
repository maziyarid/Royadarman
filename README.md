# Royadarman

24/7 dental guidance, coordination, and support centre — not a doctor and not a single clinic.

Royadarman owns the patient request, guidance, preliminary OPG review coordination, referral matching, and support. It deliberately does **not** own diagnosis, treatment, clinical records, or payments. Each partner clinic owns diagnosis, treatment, and clinical records.

This repository is the source of truth for product, architecture, and the production backend. Application code starts after Phase 0 — not before.

**Do not extend [Medical-CRM](https://github.com/maziyarid/Medical-CRM).** That product is a single-clinic ENT CRM on WordPress + PHP + MySQL. See `docs/00-reuse-verdict.md`.

## Current status

| Item | State |
| --- | --- |
| Repository | Private, created 2026-08-27 |
| Phase | 0 complete — product, legal, architecture, backend built and verified |
| Architecture | Laravel 13 modular monolith + MariaDB + database queue/workers |
| Medical-CRM reuse | Rejected (ADR-001) |
| Intake | Server-side disabled (`INTAKE_ENABLED=false`) until activation gates close |
| Tests | 172 tests / 439 assertions pass; Pint clean on 117 files |

## Read in this order

1. **`DOCUMENTATION.md`** — comprehensive system reference for every section and capability
2. `docs/00-reuse-verdict.md` — why this is a new repo
3. `docs/01-adrs.md` — accepted and proposed decisions
4. `docs/02-architecture.md` — Phase-1 working specification
5. `docs/03-phase-0.md` — work before any marketplace code
6. `docs/04-iran.md` — what Iran first locks
7. `docs/05-data-model.md` — tables we will have, and will not have

The production backend lives in `backend/` (see `backend/ARCHITECTURE.md`, `backend/DESIGN.md`, `backend/DEPLOYMENT.md`, `backend/CHECKLIST.md`). The active interactive frontend preview lives in `Front-end v1/v3-preview/`.

## Non-negotiables

1. Guidance and coordination centre — not a clinic directory and not an EMR.
2. MariaDB is the case and coordination source of truth.
3. No autonomous diagnosis; OPG review is preliminary and by an assigned licensed dentist.
4. Tehran-only home dentistry.
5. Clinical files are private: quarantine → scan → stream, never public or signed URLs.
6. Versioned localized consent, fail-closed when translations are missing.
7. Transactional outbox + idempotency; no dual-write, no duplicate cases.
8. Maps, SMS, and clinic calendars sit behind adapters.
9. WordPress is not in the transactional path.

## License

Proprietary. All rights reserved.
