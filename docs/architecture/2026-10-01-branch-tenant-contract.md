# Branch and tenant contract

Revision: 2026-10-01. Status: **proposed, not migrated**. Worker: Grok 2, issue #14, slice G2.4.

This revision does not add a table, column, foreign key, route or job. It does not accept an RPO or an RTO.

## Evidence

| Fact | Level |
| --- | --- |
| `clinics` exists: `id` ulid primary key, `name`, `city`, `area_code`, `is_active`, later `synthetic_demo_key`, `latitude`, `longitude`, `location_recorded_at` | Migration source on `a85de6c`: `2026_09_08_000300`, `2026_09_16_000100`, `2026_09_19_120000` |
| No `branches`, `organisations`, `appointments` or `slots` create | Same migration tree. `BranchTenantContractTest` reads those files |
| `patient_cases`, `clinical_documents`, `review_revisions`, `coordination_tasks`, `case_assignments` and `support_conversations` are created without `clinic_id` or `branch_id` | Those `Schema::create` blocks |
| `clinic_memberships`, `clinic_service_capabilities`, `referral_proposals`, `referral_grants` and `referral_lifecycle_events` each store `clinic_id` | Those `Schema::create` blocks |
| `referral_grants` references `proposal_id`, `case_id` and `clinic_id` as separate foreign keys. It does not reference `(proposal_id, clinic_id, case_id)` | `2026_09_08_000400` and `2026_09_11_000300` |
| App database user cannot `CREATE DATABASE`. Privileges were not expanded | Recorded 30 September restore attempt. Not re-tested as a privilege change here |
| 30 September backup hashes were verified. A full restore was not | Prior checkpoint. That backup is not a fresh backup for a later migration |
| Recovery time and point objectives | No accepted value |

## Clinic versus branch

The tenant grain is the clinic. A branch is a site inside one clinic, not a second tenant and not an organisation row. There is no organisation table distinct from `clinics`.

A future `branches` row, when a migration is actually authorised, needs both `clinic_id` and `id`, with a unique `(clinic_id, id)`. A child row needs `(clinic_id, branch_id)` referencing that pair. A `branch_id` alone is not ownership. `BranchTenantContract::scope` rejects a branch without a clinic, and it still rejects a clinic-and-branch pair because the table is absent.

Existing clinic-scoped rows stay clinic-scoped. This contract does not add `branch_id` to memberships, capabilities or referrals.

## Cases and referrals

A coordination case is not owned by one clinic. A case can be proposed to more than one clinic, so `patient_cases.clinic_id` would be false. `patient_cases.currency` stays a label. It is not a ledger.

The clinic link that exists is `referral_proposals` (`case_id`, `clinic_id`) and `referral_grants` (one grant per proposal, consent event, scope, expiry, revocation). The grant columns are not constrained to equal the proposal's clinic and case. A later migration can add a foreign key from the grant triple `(proposal_id, clinic_id, case_id)` to a matching unique key on the proposal. This revision does not add it. A matching pair is necessary and still not an authorisation to insert.

Clinical documents and review revisions stay on the case. Putting `clinic_id` only on new tables does not protect those rows. Support conversations stay case-scoped.

## Scheduling handoff

Grok 1 owns calendar correctness and scheduling services. This contract does not edit those files. `appointments` and `slots` are absent. When those tables are added, the shared key is the composite `(clinic_id, branch_id)` above, and only after `branches` exists. Capacity locking, holds and Jalali query windows stay in that lane. G1.2 can keep using current coordination tasks. It does not need this migration.

## When a migration may be considered

`migrationGate` is false in every state:

- no fresh backup for that moment: `fresh_backup_not_verified`
- backup verified but restore not proven on a separately provisioned target: `restore_target_not_proven`
- both true: `migration_not_in_this_contract`

The app user's `CREATE DATABASE` denial is not a reason to grant that privilege. The restore target is a database provisioned outside the application account. This class returns no accepted RPO or RTO. A later number needs an owner and a measured restore. Citing this file is not that decision.

## API shape

Not implemented. No route is added. Until a branch table exists, web and mobile clients have nothing to switch:

```json
{"status":"branch_table_absent","tenant_grain":"clinic","branches":[]}
```

A non-empty `branches` array would be outside this revision.

## Rollback

Delete the three files in this slice. There is no database change.
