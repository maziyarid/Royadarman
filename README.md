# Royadarman

24/7 dental guidance, coordination, and support centre — not a doctor and not a single clinic.

Royadarman owns the patient request, guidance, preliminary OPG review coordination, referral matching, and support. It deliberately does **not** own diagnosis, treatment, clinical records, or payments. Each partner clinic owns diagnosis, treatment, and clinical records.

This repository is the source of truth for product, architecture, and the production backend. The canonical deployable application is `backend/`.

**Do not extend [Medical-CRM](https://github.com/maziyarid/Medical-CRM).** That product is a single-clinic ENT CRM on WordPress + PHP + MySQL. See `docs/00-reuse-verdict.md`.

## Current status

| Item | State |
| --- | --- |
| Repository | Private, created 2026-08-27 |
| Phase | Laravel foundation built and deployed; architecture/product expansion in progress |
| Architecture | Laravel 13 modular monolith + MariaDB + database queue/workers |
| Medical-CRM reuse | Rejected (ADR-001) |
| Intake | Server-side disabled (`INTAKE_ENABLED=false`) until activation gates close |
| Tests | 406 tests / 1,371 assertions pass on deployed release `2f9763a`; Pint clean |

## Read in this order

1. **`docs/architecture/2026-09-16-canonical-architecture.md`** — current architecture and product direction
2. **`docs/architecture/2026-09-16-implementation-plan.md`** — execution order and acceptance criteria
3. **`docs/prompts/GROK-BUILD-PROMPT.md`** — complete implementation handoff prompt
4. `backend/ARCHITECTURE.md` — implemented production architecture
5. `DOCUMENTATION.md` — comprehensive system reference
6. `docs/00-reuse-verdict.md` and `docs/01-adrs.md` — historical decisions/provenance
7. `docs/02-architecture.md` through `docs/05-data-model.md` — historical Phase-0/Phase-1 planning; superseded wherever they conflict with the canonical architecture

The production application and frontend live in **`backend/`**. `Front-end v1/` and `Current Public_HTML/` are historical/non-canonical trees and must not be treated as deployable sources of truth.

## Non-negotiables

1. Persian-first dental access, discovery, referral, coordination, and CRM platform for Tehran — not an EMR and not a diagnosing provider.
2. MariaDB is the case and coordination source of truth.
3. No autonomous diagnosis; OPG review is preliminary and by an assigned licensed dentist.
4. Tehran-only home dentistry until expansion gates close.
5. Clinical files are private: quarantine → scan → approve → authorized stream, never public clinical storage.
6. Versioned localized consent, fail-closed when translations are missing.
7. Transactional outbox + idempotency; no dual-write, no duplicate cases.
8. Maps, SMS, payments, and clinic calendars sit behind adapters.
9. WordPress is not in the transactional path.
10. `backend/` is the only deployable application; legacy frontend trees are not production sources.

## License

Proprietary. All rights reserved.
