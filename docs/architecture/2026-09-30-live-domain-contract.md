# Roya Darman domain contract — live baseline

Status: documented from production on 30 September 2026. Not a migration. Not deployed application code. RPH-96 stays Planning.

## Evidence

- Host: `server.royadarman.com` (`host_d3b5541db7acae2a`).
- Application: `/home/royadarman/apps/royadarman-backend`. Document root boots that tree.
- PHP 8.3.33, Laravel 13.29.0, MariaDB 10.11.19, Laravel connection driver `mysql`.
- `artisan migrate:status`: 27 migrations Ran, including `2026_09_29_160000_add_username_to_users` batch 11.
- `information_schema`: 61 tables, all InnoDB. No MyISAM.
- No production `.git` directory and no production `tests/` directory.
- Column, index and foreign-key inventory was read from `information_schema` during the backup. No row contents were exported into this contract.

## Backup this contract relies on

Directory (mode 0700, owner `royadarman`):

`/home/royadarman/royadarman-cpanel-backup-20260930T154757Z-before-rph96-contract`

| File | SHA-256 |
| --- | --- |
| `database.sql.gz` | `4ebcc9db14d4b4bae167b0f17bffa55bfde5a31c90b3533c0d750cf0d7243165` |
| `site-and-app.tar.gz` | `84098d8fcb06643b8ca69ce806903c79936cc91b337279e416e799f2b6c784cf` |

`sha256sum -c` and `gzip -t` passed. The dump used `--single-transaction` plus routines, triggers and events. That is a consistent InnoDB snapshot because every table is InnoDB. The temporary client file was removed. The database name and password remain only inside the archived `.env`.

Included paths: `public_html`, `apps/royadarman-backend` (including `.env`, `vendor` and `storage`), and `private_uploads` (`quarantine` and `approved`).

At backup time `private_uploads/quarantine` and `approved` contained 0 files. `storage/app` contained 4 files totalling 325 bytes, of which `storage/app/private` contained 1 file of 14 bytes. Those counts are not a claim about historical uploads.

Restore rehearsal did not pass. The application database user cannot `CREATE DATABASE`, so the dump was not imported into an isolated database and dropped. Archive integrity is verified. A full restore is not.

Earlier 29 September archives exist. The password and delivery edits timestamped 29 September 15:55–16:40 UTC are later than the named pre-change archives from that morning. This 30 September backup is the one that covers the current tree.

## What the live schema already is

MariaDB has no native row-level security. Tenant isolation is application policy plus foreign keys. A foreign key to `users.id` does not imply a clinic boundary.

### Identity

`users` is the account. `id` is `bigint` autoincrement. `role` is `varchar(32)`, not a database enum. The PHP enum is still exactly: `patient`, `coordinator`, `clinician`, `clinic_rep`, `owner`, `tech_admin`. One column cannot represent the twelve planned workspaces.

`username` is nullable, unique, `varchar(32)`. `email` and `phone_hash` are unique. `phone` is `text` (treat as sensitive; not copied here). Password, TOTP and recovery codes exist. `is_active` and `last_authenticated_at` exist.

`practitioners` is one row per user (`user_id` unique), with `licence_hash` unique and `credential_status`. It is not a per-clinic employment record.

`clinic_memberships` is the existing clinic link: unique `(clinic_id, user_id)`, plus `membership_role varchar(32)`, `active_from`, `active_until`. It is not tied by a foreign key to `users.role` or to `practitioners`.

`passkeys` belong to `users`. Laravel `sessions` exists. There is no separate device-inventory table in the inspected foreign keys.

### Clinic, not branch

`clinics` has name, city, area code, active flag, optional coordinates and `synthetic_demo_key`. There is no `branches` table and no organisation table distinct from `clinics`. A branch cannot be enforced until that table exists.

### Coordination case

`patient_cases` is the support/coordination aggregate. It has `public_reference`, patient user, service type, status, priority, contact fields, Tehran area, budget band, `currency varchar(3)`, `budget_input_unit`, source language, coordinator, and `version` for optimistic locking.

It has no `clinic_id`. Clinic attachment is through `referral_proposals` and `referral_grants`, not through the case row. Clinical documents hang off the case, so a document is not schema-bound to a clinic.

`currency` and `budget_band` are labels. They are not a ledger. There are no invoice, payment, instalment or cheque tables.

### Documents and reviews already present

`clinical_documents`: case, uploader, optional consent event, storage disk/key (`storage_key` unique), mime, size, sha256, status, scan fields, `retention_until`, soft `deleted_at`.

`scan_attempts`: unique `(document_id, attempt_number)`.

`review_revisions`: case, document, clinician user, `revision_number` unique per case, `supersedes_id` self-reference, narrative fields (`image_adequacy`, `observations`, `limitations`, `options`, `recommended_next_step`), nullable `signed_at`.

`publication_events`: an event name on a review revision, with actor and timestamp.

`document_access_events` records actor, case and document.

There is no tooth, surface, finding, annotation or patient-released projection table. A signed narrative revision is not the RPH-109 tooth-level publication workflow. `signed_at` is a nullable timestamp. The database does not require the signer to be a verified practitioner.

### Consent, referral, support, outbox

`consent_events` record purpose, decision, policy version, subject user, optional case, and `revoked_at`.

`referral_proposals` point at a case and a clinic. `referral_grants` are one per proposal, require a consent event, carry a text `scope`, and have `expires_at` and `revoked_at`.

`coordination_tasks` and `support_conversations` / `support_messages` are case-scoped. They are the support workspace. They are not clinical signing authority.

`outbox_events.deduplication_key` is unique. `notification_deliveries` is unique on `(outbox_event_id, channel)` and on `(channel, provider_reference)`.

`audit_events` stores actor, action, resource type/id, result, correlation id, and free-text `reason` and `context`. The table exists. This contract does not claim writers keep clinical text or secrets out of `context`.

`idempotency_records` is unique on `(actor_user_id, operation, idempotency_key)`.

## What must not be inferred

- Six PHP roles plus `clinic_memberships.membership_role` are not the twelve-workspace matrix.
- A coordinator calendar or Jalali helper is not an appointment slot model. `appointments` and `slots` are absent.
- `review_revisions` text is not a per-tooth chart. Unknown teeth must not be displayed as healthy; that rule cannot be enforced by this schema because tooth rows do not exist.
- `publication_events` is not proof a patient can see a released dental status.
- Empty private-upload directories are not proof the scanner pipeline fails or succeeds.
- Role row counts are not proof of a verified clinician.
- HTTP 200 on a login page is not a successful password sign-in.

## Invariants to preserve when extending

1. Do not replace this monolith or this MariaDB database without a new measured decision.
2. Expand/contract only. Do not drop `users.role` until every reader uses memberships.
3. New tenant-owned rows need a clinic (and later branch) column plus a composite foreign key. Adding `clinic_id` only on new tables does not protect existing `clinical_documents`, which are case-scoped.
4. Clinical signing stays a practitioner credential plus an explicit grant, not `users.role = owner` and not `membership_role` alone.
5. Money, when added, uses integer minor units and an explicit currency. Do not overload `patient_cases.currency`.
6. Appointment capacity, when added, needs a transaction that locks the resource row. A unique appointment id is not a capacity lock.
7. Outbox rows commit with the domain change. Provider sends stay outside the transaction and use the existing deduplication key.
8. Audit payloads stay identifiers and action names. Do not copy document bodies, passwords, tokens or phone numbers into `audit_events.context`.
9. No migration runs until a backup of at least this quality exists for that moment. This backup does not make a later migration reversible if it rewrites existing clinical text.

## Deliberately not created in this change

Branches, twelve-role grants, guardians, appointment slots, tooth findings, ledgers, cheque events and video sessions. Those wait on RPH-58 and the open clinical, legal, merchant, holiday and retention decisions in RPH-85. No named owner was assigned here.

## Next implementation step

Still not a clinic scheduler. The smallest code change that matches this contract is an additive, tested membership/permission map that reads `users.role` and `clinic_memberships` without widening support users into clinical records. It needs a fresh backup if this one is no longer current, and it needs tests outside production because production has no `tests/` directory.
