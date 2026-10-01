# G2 reference for other workers

Snapshot: 2026-10-01. Worker: Grok 2. Issue: #14.

This is a map of the draft stack. Nothing in it is deployed. Nothing in it is an agreed clinical, finance, retention, or merge policy. A later commit can supersede a sentence; the class and the test are the check.

## Stack

Read the top draft and walk the base links. Do not rebase this stack onto main while another worker owns the same path.

| Slice | Draft | Head at this snapshot | What it is |
| --- | --- | --- | --- |
| Membership map | [#21](https://github.com/maziyarid/Royadarman/pull/21) | `cd1517a513d27574fa61344f3f965f8ee795bd7b` | Live assignment bytes. Not a new role. |
| Assignment regressions | [#22](https://github.com/maziyarid/Royadarman/pull/22) | `94376951fd4fd1f7d980926b5be7c69b6a8b5d16` | SQLite tests. MariaDB race not run. |
| Workspace catalogue | [#23](https://github.com/maziyarid/Royadarman/pull/23) | `a85de6c473df0ccfcd455f21a25845ed4270d2a4` | Twelve names, all fail closed. |
| Branch tenant | [#25](https://github.com/maziyarid/Royadarman/pull/25) | `21f6951c4dd78471a0e3079e511b332421e43fb7` | Clinic grain. No branch table. |
| Audit | [#26](https://github.com/maziyarid/Royadarman/pull/26) | `71ad7e8ed2f705796dd6da2ed858a06682184945` | No retention. One assignment write uses `AuditEvent`. |
| OPG | [#27](https://github.com/maziyarid/Royadarman/pull/27) | `8a35c56fca08b3bbdf69306ad241dc51ef0f5da0` | Scan is not a diagnosis. No tooth list. |
| Finance | [#29](https://github.com/maziyarid/Royadarman/pull/29) | `0b9709bd3008df1c2a36f24aae5fdaa14cd1be75` | Labels only. No amount and no ledger. |
| Inventory and guardian | [#30](https://github.com/maziyarid/Royadarman/pull/30) | `361bb206b08c892fcf9c6109fcacfa6f9b7a2b54` | Those tables are absent. |
| Patient review projection | [#31](https://github.com/maziyarid/Royadarman/pull/31) | `a41bb43dac62933442a84f954477ea57302ed145` | Patient list requires `signed_at` and `event = published`. |

The follow-up commit `38d1b6756db7e70cbd75dc95cdea1a47acab0509` reads the patient list through `ReviewRevision` and the patient's own file name through `ClinicalDocument`. Commit `c0ce090c7321b5a8cfff7eceeff600e48a475a11` on the same pull request does that for the clinician document list too, and it still requires status `approved` plus an accepted consent whose `revoked_at` is null. Neither change grants the file bytes. Commit `4e0bd17d415d601e527130026a9d11d1bb6c0515` on `g2/opg-content-status-20261001` makes `DocumentController::content` return `document.rejected` or `document.scan_failed` only to the owning patient or to the assigned verified clinician with current consent. Anyone else gets 404, and no access row is written. Commit `483c800334ef7fb30147da353459a2a631b8f140` makes `PanelController::clinicianPanel` count `published_reviews` only when `signed_at` is set and a `published` event exists. The `match ($user->role)` line is unchanged, so draft #28 can still replace that line with `dashboardFamily()`. A signed review with no event is in neither count. On publish, `StaffCaseController` sets `supersedes_id` to the same clinician's nearest earlier published revision on that case. Another clinician's revision is not linked, and a later revision number is not treated as earlier. The patient page still lists every published revision. `DashboardService` already hid a superseded row and was not edited.

#24 is Grok 1's calendar. Do not edit it from this lane.

## Parallel draft that is not this stack

[#28](https://github.com/maziyarid/Royadarman/pull/28) (`vibe/p01-identity-tenancy-f264e5`, head `6da7ca04072e64e8d8d1701f145eecfc42a4cea0` at this snapshot) is based on main, not on this stack. It adds `StaffCapabilities`, more `UserRole` cases, `isClinicalSigner()` for `clinician`, and `finance.view` for accountant, owner, and superadmin.

That draft is not merged here and is not an agreed grant. G2 classes still return false for a title-based document read and still have no money amount. Do not edit `UserRole.php` or `StaffCapabilities.php` from the G2 lane while #28 is open. Do not treat `finance.view` or `isClinicalSigner()` as permission to sign or to post a ledger.

## Reason codes other workers can assert

These classes are not called by the controllers, except the patient query, which now matches `OpgAccessContract::releasedText` without calling it.

| Class | A clean or successful-looking input still denies with |
| --- | --- |
| `MembershipPermissionMap` | The live map. Illegal pairs deny. Six `users.role` values stay. |
| `WorkspaceGrantCatalogue` | `grant_not_activated` and the other closed reasons. No title signs. |
| `BranchTenantContract` | `migration_not_in_this_contract` even when backup flags are passed. |
| `AuditAccessContract` | `redaction_passed_not_a_writer`. `authorisesClinicalRead` is `privileged_read_not_authorised`. |
| `OpgAccessContract` | `bytes_not_granted_by_this_contract`, `malware_verdict_not_a_diagnosis`, `released_text_not_a_byte_grant`. |
| `FinanceIntegerContract` | `band_is_not_an_amount`, `amount_column_absent`, `conversion_not_stored`, `ledger_table_absent`. |
| `InventoryGuardianContract` | `stock_table_absent`, `guardian_column_absent`, `patient_merge_not_defined`. |

## Still not decided

A fresh backup and a separate restore target before any migration. A named signer, tooth taxonomy, and retention period. A merchant and a money factor. A guardian rule, a patient-merge policy, and a stock rule. Device and auth stay with Perplexity. Calendar stays with Grok 1. Shared frontend stays with Codex.

## Rollback of the projection fix

Revert the projection commit. The patient query returns to `signed_at` alone. Do not restore a database.
