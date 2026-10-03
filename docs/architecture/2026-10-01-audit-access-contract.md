# Audit access contract

Revision: 2026-10-01. Status: **proposed, not wired**. Worker: Grok 2, issue #14, slice G2.5.

This revision does not add a column, a retention period, a route, or a privileged clinical read. `AuditAccessContract` is not called by the existing writers.

## Evidence

| Fact | Level |
| --- | --- |
| `audit_events` columns are `id`, `actor_user_id`, `action`, `resource_type`, `resource_id`, `result`, `reason`, `context`, `correlation_id`, `created_at` | Migration `2026_08_31_000100` on `21f6951c`. No `clinic_id`, `tenant_id`, or retention column |
| `AuditEvent` encrypts `reason` and `context`, hides `context`, and does not use Laravel's updated_at | `app/Models/AuditEvent.php` |
| Dashboard recent audit selects `id`, `action`, `actor_user_id`, `created_at` only | `DashboardService` on this base. Not changed here |
| `CoordinatorAssignment` previously inserted `context` with `json_encode` through `DB::table`, which skips those casts | Source before this commit. The write now uses `AuditEvent::query()->create` with the same action and `coordinator_user_id` |
| Other writers still call `AuditEvent::query()->create` themselves | Read on the isolated checkout. Not modified: policy, support, admin, readiness, tasks, documents, sessions, demo seed, retention command |
| `config/royadarman.php` `retention.document_days` stays blank-fail-closed | Existing comment in that file. This slice does not set it and does not treat it as an audit retention decision |
| Who may read a clinical record by title, and how long audit rows are kept | Open. Not decided here |

`document_access_events` is a separate case/document access log. It is not this table and it is not changed.

## What a row may contain

Actor (`actor_user_id`, nullable for the system assignment), action name, resource type and id, result (`success`, `failure`, or `denied`), correlation id, and `created_at`. The reason, when present, is a short label such as `least_loaded_active_coordinator`. Context, when present, is a structured list of identifiers. It is not a JSON string.

Refuse, and do not store:

- a phone number, email address, patient name, password, token, OTP, recovery code, licence number, message body, document body, or clinical observation
- `clinic_id` or `tenant_id` on this table; the column does not exist, and a case is not one clinic
- `retention_days`; no period is accepted. Null is not zero and not "keep forever"

A payload that passes those checks is still not a write. `assess()` returns `redaction_passed_not_a_writer`.

## Privileged read

`authorisesClinicalRead` is false for every title, including owner, tech_admin, superadmin, dentist, and clinician. An audit row does not grant the read it describes. Sensitive-access policy remains an open decision.

## One writer changed

`CoordinatorAssignment::assignInitial` no longer inserts plaintext JSON into `audit_events`. The action stays `case.coordinator_auto_assigned`. The context stays the coordinator's user id. Encryption is the existing model cast, not a new algorithm. Session audit in `SessionInventoryService` is unchanged.

## API shape

Not implemented. No route is added.

```json
{"status":"audit_contract_not_wired","retention_days":null,"privileged_clinical_read":false}
```

A numeric `retention_days` or `privileged_clinical_read: true` would be outside this revision.

## Rollback

Revert this commit. The assignment write returns to the direct insert. No database restore. Rows already stored are left as they are.
