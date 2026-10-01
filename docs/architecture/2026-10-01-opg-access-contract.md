# OPG access contract

Revision: 2026-10-01. Status: **proposed, not wired**. Worker: Grok 2, issue #14, the remainder of G2.5.

This revision does not add a table, a signer list, a tooth taxonomy, a retention period, or a route. `OpgAccessContract` is not called by the scanner, the document policy, or the review controllers. The live file gate remains `ClinicalDocumentPolicy`.

## Evidence

| Fact | Level |
| --- | --- |
| An upload is stored on `opg-quarantine` with status `quarantined` | `QuarantineClinicalDocument` |
| `opg-quarantine` and `private-opg` set `serve` to false and are not public disks. `public` and `public-cms` are public | `config/filesystems.php` |
| The scanner defaults off. A clean malware result moves the same hash to `private-opg` and sets status `approved`. An unclean result sets `rejected` and deletes the quarantine object. A failed scan sets `scan_failed` | `config/royadarman.php` and `ScanClinicalDocument` |
| Status values are `quarantined`, `scanning`, `approved`, `rejected`, `scan_failed`, `deleted` | `DocumentStatus` |
| `storage_key`, `scan_reference`, and `scan_result` are hidden. The original name is encrypted | `ClinicalDocument` |
| A clinician can view an approved document only with an active clinical assignment, accepted unrevoked consent, and a verified unexpired credential. A patient can view their own approved document. No other role is granted by that policy | `ClinicalDocumentPolicy` |
| A review must point at a clinical document. The text columns are encrypted. Publishing sets `signed_at` and inserts `publication_events.event = published` in one transaction | Migrations through `2026_09_11_000200` and `StaffCaseController` |
| The patient panel used to list reviews with `signed_at` set and did not read `publication_events`. That gap is closed on `g2/patient-review-projection-20261001`: the patient query requires a `published` event as well | `PanelCaseController` |
| `retention_until` is written only when `ROYADARMAN_DOCUMENT_RETENTION_DAYS` is a positive number. The config default stays blank | `ScanClinicalDocument` and `config/royadarman.php` |
| No `teeth`, `tooth_findings`, or `treatment_stages` table | Migration scan on this base |
| Who may sign, which tooth codes exist, and how long a file is kept | Open. Not decided here |

## Scan is not a diagnosis

`clean`, `infected`, and `scanner_unavailable` are malware-scan verdicts. `interpretScan` refuses them as a diagnosis. A word such as `caries` is not a known scan verdict and is not turned into one.

## Bytes

Quarantine, scanning, rejected, and failed files are not readable through this contract. An approved file on `private-opg` is still not granted by this class: the result is `bytes_not_granted_by_this_contract`. `public` and `public-cms` are refused for an OPG object.

A title does not grant the read. That includes owner, tech_admin, superadmin, dentist, clinician, coordinator, and patient. The existing policy can still allow the owning patient or the assigned clinician. This class does not replace that policy.

## Released review

Unsigned text is withheld. `signed_at` without a `published` event is withheld. Both together describe released review text, not a grant of the image bytes.

The patient panel now requires both `signed_at` and a `published` event. That follow-up does not grant the image bytes.

## What is not decided

No retention number. Null is not zero and not "keep forever". No tooth, surface, or treatment-stage list. No new signer. The publisher remains the clinician already named on that revision, with the existing credential and assignment checks. Those checks are not re-agreed here.

## API shape

Not implemented. No route is added.

```json
{"status":"opg_contract_not_wired","scan_is_diagnosis":false,"retention_days":null,"tooth_taxonomy":null,"privileged_document_read":false}
```

## Rollback

Revert this commit. No database restore. The scanner, disks, and policies stay as they were.
