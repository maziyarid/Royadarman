# Additive workspace grant contract

Revision: 2026-10-01. Status: **proposed, not activated**. Worker: Grok 2, issue #14, slice G2.3.

This document does not change `users.role`, clinic membership assignment, policies, routes or the database. It is not an agreed access policy. Naming a workspace here does not grant it.

## Evidence

| Statement | Level |
| --- | --- |
| `users.role` is still exactly `patient`, `coordinator`, `clinician`, `clinic_rep`, `owner`, `tech_admin` | Repository enum on main `f99210e`, unchanged by this slice |
| Twelve workspace rows in roadmap section 2 | Roadmap `docs/roadmap/2026-09-30-royadarman-roadmap.md`, not a permission implementation |
| Clinical signing is a practitioner credential plus an explicit grant, not `users.role = owner` and not `membership_role` alone | Live domain contract invariant 4 |
| No `branches` table and no organisation table distinct from `clinics` | Live domain contract, 30 September inspection |
| Eligible clinical signers, superadmin sensitive reads, guardian authority and finance approval limits | Open. RPH-85 / RPH-101 / RPH-110. Not decided here |

## Identifiers

Stable ids for later grants. They must not be written into `users.role`.

| Id | Roadmap row | Task |
| --- | --- | --- |
| `owner` | Owner / Maziyar | RPH-99 |
| `developer` | Developer / Dev | RPH-100 |
| `superadmin` | Superadmin | RPH-101 |
| `supervisor` | Supervisor | RPH-102 |
| `receptionist` | Receptionist | RPH-103 |
| `accountant` | Accountant | RPH-104 |
| `customer_support` | Customer support | RPH-105 |
| `treatment_specialist` | Treatment specialist / کارشناس درمان | RPH-105 |
| `clinic_manager` | Clinic manager / clinic organisation | RPH-106 |
| `dentist` | Dentist / clinician | RPH-107 |
| `clinical_staff` | Clinical staff / assistant / clinic staff | RPH-107, RPH-112 |
| `patient` | Patient / client | RPH-108 |

`guardian` is not a thirteenth workspace. Guardian authority is a decision-gated relationship to a patient record (RPH-110). A family phone is not that authority.

`tech_admin`, `coordinator` and `clinic_rep` are current account roles. They are not workspace ids. This slice does not alias `tech_admin` to `developer`, `coordinator` to `customer_support` or `treatment_specialist`, or `clinic_rep` to `clinic_manager`.

The workspace-to-enum label pairs recorded here, not grants or literal roadmap slash-pairs, are `owner`/`owner`, `dentist`/`clinician` and `patient`/`patient`.

## What a later grant row would have to prove

No migration in this slice. A later table, after a fresh backup and a separately provisioned restore target, would need at least:

- one person, many grants, including several workspaces
- `workspace_id` from the list above, never a replacement of `users.role`
- scope kind: `personal`, `organisation`, `clinic`, `branch`, `team` or `assignment`
- only `personal` and `clinic` are schema-backed today; `branch` cannot be enforced until a branch table exists
- `expires_at` and `revoked_at`
- granting actor and a reason that is not clinical text, a phone number or a secret
- uniqueness of an active `(user, workspace, scope kind, scope id)`, so a repeat is an update rather than a second active row

Revocation is `revoked_at` set immediately. Expiry is `expires_at <= now`. A later reader on UI, API, search, jobs, export, files and cache must treat either state as absent. This slice does not add those readers and does not claim cache invalidation.

Invitation, acceptance, suspension, offboarding and rehire are named lifecycles from the roadmap. They are not stored.

Clinic organisation profile stays on the clinic. It is not the manager's personal account.

## Fail-closed evaluation

`WorkspaceGrantCatalogue::evaluate` denies every workspace and every action below. A current verified credential, a reviewer membership, or an owner/superadmin/dentist title does not flip that result. `titleGrantsClinicalSigning` is false for every string, including `dentist`, `owner` and `superadmin`.

Actions named only so callers cannot invent a quieter allow:

- `clinical_sign`, `clinical_read`, `clinical_draft`
- `support_conversation_read`
- `finance_approve`
- `scheduling_write`
- `tenant_admin`
- `diagnostic_read`

Reasons: `unknown_workspace`, `unknown_action`, `grant_revoked`, `grant_expired`, `invalid_expiry`, `grant_not_activated`. None of them means allowed. Revocation is reported before expiry.

Existing case, document and support policies stay as they are. Membership still does not open a clinical record or a support conversation. This catalogue must not be wired in as a replacement for those positive authorised paths.

## API shape for Codex and mobile

Not implemented. No route is added. Web, Android and iOS, when a later slice activates grants, use the same versioned API. Until then the only honest body is:

```json
{"status":"proposed_not_activated","grants":[]}
```

A non-empty `grants` array would be outside this revision. Do not build a screen that treats a workspace title as clinical, finance or support authority.

## Rollback

Delete the three files in this slice. There is no database change and no deployed PHP to restore.
