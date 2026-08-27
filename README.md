# Royadarman

Managed dental marketplace.

Royadarman owns the patient request, matching, appointment hold, payment, support, and clinic settlement. Each clinic owns diagnosis, treatment, and clinical records.

This repository is the source of truth for product and architecture. Application code starts after Phase 0 — not before.

**Do not extend [Medical-CRM](https://github.com/maziyarid/Medical-CRM).** That product is a single-clinic ENT CRM on WordPress + PHP + MySQL. See `docs/00-reuse-verdict.md`.

## Current status

| Item | State |
| --- | --- |
| Repository | Private, created 2026-08-27 |
| Phase | 0 — product, legal, operations |
| Architecture | Modular monolith + PostgreSQL/PostGIS + workers |
| Medical-CRM reuse | Rejected (ADR-001) |
| Open product decisions | Country, fee scope, patient choice, booking mode, legal settlement |

## Read in this order

1. `docs/00-reuse-verdict.md` — why this is a new repo
2. `docs/01-adrs.md` — accepted and proposed decisions
3. `docs/02-architecture.md` — system, modules, matching, booking, money
4. `docs/03-phase-0.md` — work before any marketplace code
5. `docs/04-iran.md` — what Iran first locks
6. `docs/05-data-model.md` — tables we will have, and will not have

## Non-negotiables

1. Marketplace, not a clinic directory and not an EMR.
2. PostgreSQL/PostGIS is the booking and money source of truth.
3. No payment without a viable clinic hold.
4. Hosted checkout. Signed webhooks only. Immutable double-entry ledger.
5. Instant slots and manual clinic acceptance in MVP.
6. Operations console is a first-class application.
7. Maps, PSP, SMS, and clinic calendars sit behind adapters.
8. WordPress is not in the transactional path.

## License

Proprietary. All rights reserved.
