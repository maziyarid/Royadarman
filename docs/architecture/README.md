# Royadarman Architecture Index

## Current authority
1. [`2026-09-16-canonical-architecture.md`](./2026-09-16-canonical-architecture.md) — reconciled current/target architecture.
2. [`2026-09-16-implementation-plan.md`](./2026-09-16-implementation-plan.md) — execution order and acceptance criteria.
3. [`../../backend/ARCHITECTURE.md`](../../backend/ARCHITECTURE.md) — implemented Laravel production architecture.

## Source/provenance
- [`2026-09-16-suggested-architecture-source.md`](./2026-09-16-suggested-architecture-source.md) — normalized repository reconciliation of the user-provided architecture DOCX. It preserves the document’s core product, security, UX and audit findings while explicitly distinguishing the obsolete generic stack proposal from the later repository-grounded correction.

## Build handoff
- [`../prompts/GROK-BUILD-PROMPT.md`](../prompts/GROK-BUILD-PROMPT.md) — complete implementation prompt for Grok.

## Governing decision
Royadarman remains a Laravel 13 / PHP 8.3 / MariaDB modular monolith with `backend/` as the only deployable source of truth. The old Node/PostgreSQL/PostGIS proposal is not a migration target.
