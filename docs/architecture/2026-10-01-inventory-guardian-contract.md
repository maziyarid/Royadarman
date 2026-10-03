# Inventory and guardian contract

Revision: 2026-10-01. Status: **proposed, not wired**. Worker: Grok 2, issue #14.

This revision does not add a table, a stock rule, a guardian relationship, or a patient-merge policy. `InventoryGuardianContract` is not called by CMS, sessions, referrals, or finance.

## Evidence

| Fact | Level |
| --- | --- |
| No `stock_items`, `stock_batches`, `purchase_orders`, `guardians`, `guardian_relationships`, `patient_merges`, or `maintenance_records` table | Migration scan on `0b9709bd` |
| `users` and `patient_cases` have no guardian column | Their `Schema::create` blocks |
| `job_batches` has `total_jobs`, `pending_jobs`, and `failed_jobs`. It is the queue table, not a product batch | `0001_01_01_000002_create_jobs_table.php` |
| `SessionInventoryService` names sessions, not SKUs or stock | That file. Not modified. Session behaviour stays with Perplexity |
| CMS tag merge updates `cms_post_tag` and deletes the source tag | `CmsTagController::merge`. Not modified. It is not a patient merge |
| `expires_at` exists on OTP challenges, practitioner credentials, and referral grants | Those migrations. A grant expiry is not a product expiry |
| The September data-model note lists `GuardianRelationship` and a ledger as a historical design that was not implemented | `docs/05-data-model.md`, marked superseded |
| The 30 September live contract already left guardians, ledgers, and cheque events uncreated | `docs/architecture/2026-09-30-live-domain-contract.md` |

## What is refused

A SKU, a quantity, and a date, including zero, a negative quantity, and a past date, all return `stock_table_absent`. No non-negative rule and no expiry rule is adopted, because there is no stock table to attach one to.

A purchase amount, including zero, is `purchase_table_absent`. This does not store a price and does not reuse `patient_cases.currency`.

Linking a guardian is `guardian_column_absent`.

Merging two named different patients is `patient_merge_not_defined`. The same id is `merge_same_patient`. An empty id is `patient_not_named`. None of those is a merge procedure.

## What is not decided

Who may act for a minor, whether two patient rows may be combined, and how a clinic counts stock, batches, purchases, or maintenance. Those need a named owner. A migration still needs a fresh backup and a separately provisioned restore target.

## API shape

Not implemented. No route is added.

```json
{"status":"inventory_guardian_contract_not_wired","stock":false,"guardian":false,"patient_merge":false}
```

## Rollback

Revert this commit. No database restore. The CMS tag merge and the session inventory stay as they were.
