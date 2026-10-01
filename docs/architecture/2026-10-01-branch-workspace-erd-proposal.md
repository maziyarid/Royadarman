# Branch & Workspace ERD Proposal — P01 Identity & Tenancy (PROPOSAL ONLY)

Date: 2026-10-01
Task: RPH-124 (M3) — lane P01 (identity, tenancy, permissions)
Status: **PROPOSAL — no migration, no UI, no implementation**

## Purpose

This document maps the tables that PR #28 (branch `vibe/p01-identity-tenancy-f264e5`, P01 lane) actually reads, writes, or constrains in its shipped code and tests. It is a data-model reference for the host's conflict-map review; it names only tables that this lane's commits engaged. Every table marked PROPOSAL below is an *aspiration for a future schema refinement*, not a change made in this PR. Nothing here is implemented.

## Existing tables this lane touched (no schema change)

These tables already exist in migrations; PR #28 only reads/writes them:

- `users` — `role` is a plain `string(32)`; PR #28's granular roles (7 new role strings) live in code (`UserRole` enum + `StaffCapabilities` map) and need no migration.
- `clinics` — tenancy root; read by referral-grant and network-admin flows.
- `clinic_memberships` — per-clinic user membership with `membership_role`, `active_from`, `active_until`, `unique(clinic_id, user_id)`; the grant lifecycle requires an **active** membership (`ReferralGrantLifecycleTest`).
- `referral_grants` — per-case→clinic grant: `scope` JSON, `granted_at`, nullable `expires_at`, nullable `revoked_at`; PR #28 enforces the non-revoked / non-expired check per request.
- `consent_events` — per-subject consent decision bound to a `policy_version_id`; the grant lifecycle requires an accepted, non-revoked consent event per request.
- `audit_events` — append-only audit log; PR #28 added the `staff.sessions_revoked` action (written, no schema change).
- `patient_cases`, `referral_proposals`, `support_conversations` — read by policies/controllers this lane touched.

## PROPOSAL tables (not implemented; future schema candidates)

All of the following are **PROPOSAL** only. No migration was written, and none will be written in this lane without a separate instruction.

1. **PROPOSAL `workspace_memberships`** — generalizes `clinic_memberships` for a multi-context future (clinic, network, operations workspaces). Columns: `id ulid PK`, `user_id FK→users cascadeOnDelete`, `workspace_type string(32)`, `workspace_id ulid`, `membership_role string(32)`, `active_from`, `active_until nullable`, `unique(workspace_type, workspace_id, user_id)`. Rationale: PR #28's capability checks (`support.view`, `coordination.assign`, `network.manage`) are user-role based; a per-workspace membership table would allow scoping capabilities to a workspace instead of globally. **PROPOSAL — not opened, not implemented.**
2. **PROPOSAL `capability_assignments`** — a per-user or per-membership capability override table keyed by the `StaffCapabilities` names (`support.view`, `coordination.assign`, `network.manage`, …). Would replace/augment the static role→capability map with a data-driven override path (still deny-by-default). **PROPOSAL — not opened, not implemented.**
3. **PROPOSAL `referral_grant_events`** — append-only lifecycle journal for grants (`granted`, `revoked`, `expired`, `reassigned`), joining `referral_grants` and `audit_events` semantics, so grant-state history is queryable without parsing `audit_events.context`. **PROPOSAL — not opened, not implemented.**

No branches/organizational-hierarchy table is proposed in this document (out of scope per task constraints). No UI, no migration, no seeder accompanies this proposal.

## Relationship sketch (existing + proposed)

```
users 1───N clinic_memberships N───1 clinics            (existing)
clinics 1───N referral_grants N───1 patient_cases        (existing)
users 1───N consent_events N───1 policy_versions         (existing)
users 1───N audit_events (actor)                          (existing)
users 1───N [workspace_memberships] 1───N [capability_assignments]   (PROPOSAL)
referral_grants 1───N [referral_grant_events]             (PROPOSAL)
```

## Non-goals

- No migration, no seeder, no UI, no workspace chooser.
- SQLite in-memory tests are not MariaDB proof; any future proposal implementation must be verified on MariaDB 10.11.
- Not this lane: RTL/LTR, Blade/CSS, mobile stack, ADR-0001 ratification.
