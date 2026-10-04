# Tenancy interface v1.1 — W02

Status: implemented and TESTED_ISOLATED on the W02 branch; not integrated, deployed or accepted.
Base: 9a16918997dfa064364fda47c57b18b4f5e40618. Existing G01/G02 path grants apply.
The 08:23 first snapshot was READ_CHECK_ONLY. This revision adds a real transaction consumer.

## Persistent identity and compatibility

`clinics.id` (ULID) remains the clinic/tenant key. `organisations.clinic_id` is its one-to-one business profile, not an implicit multi-clinic parent.
`clinic_branches.id` is a ULID with unique `(clinic_id,id)` and `(clinic_id,code)`.
`workspace_memberships` has a composite `(clinic_id,branch_id)` foreign key to the branch pair and a non-null unique `(branch_id,user_id,workspace_role)` assignment.
Existing users, six `users.role` values, clinic_memberships, cases, clinical policies, consent and credentials remain independent and unchanged.
No patient data is moved, merged, copied or made clinic-visible by this migration.

## Contracts

`WorkspaceAccess::resolve(int actorId, string membershipId, ?string clinicId=null, ?string branchId=null, ?int version=null): WorkspaceContext`
returns `{version:1, actor_user_id, clinic_id, branch_id, membership_id, workspace_role, membership_version}`. PHP properties are actorId, clinicId, branchId, membershipId, workspaceRole, membershipVersion.
The actor must come from authenticated server identity or a persisted, authorised job actor, never a request-supplied actor ID. A context is untrusted data, not a capability token.

`authorize(WorkspaceContext, string permission)` rechecks membership and permission for a read. `allows()` converts only 403/404 to false; operational errors propagate. Neither is an atomic write boundary.

`run(WorkspaceContext, string permission, Closure operation): mixed` performs current locking reads and the callback in the SAME default database connection transaction. It returns the callback result, revalidates before returning, and rolls back callback writes on exception or loss/expiry of eligibility. It never automatically retries the callback.

Consumers enter run BEFORE acquiring their own resource/invoice/document locks. Query their records by both clinic_id and branch_id, then acquire domain locks in a stable order. They must retain domain-specific patient/assignment/consent/credential/amount rules. The callback may not commit/roll back, change connection, execute DDL, contact a provider, send messages or perform irreversible external effects. Persist an agreed outbox event in the transaction; transports run after the OUTERMOST commit.

Nested use inside an existing transaction is supported through locking reads; it does not inherit that transaction's older repeatable-read snapshot for authorisation. Locks remain until the outer transaction ends. Other plain domain reads may still be snapshots; consumers must use current domain reads where required. Expiry is rechecked before the envelope returns, not certified at some later caller-controlled outer commit.

## Lock ordering and concurrency limits

Every W02 management command and run() first locks the SAME existing clinics row, then the organisation/branch where applicable. This deliberately serialises W02 operations within one clinic. It is a conservative correctness-first design, not a throughput benchmark.
User sets needed by membership assignment/revocation are locked in ascending numeric ID order. Revoke locks current owner-membership candidates under the clinic mutex before locking their users, then performs a current-read last-owner check. run() holds actor/membership/credential rows and rechecks eligibility after the callback.
No writer may acquire a domain resource lock and then enter this envelope, or nest cross-clinic operations. Existing auth/account/credential lifecycle writers and new modules require independent lock-order review. In particular, protecting the last owner during a membership revoke does not certify every possible account deactivation path.
MariaDB constraints, concurrent duplicate grants, opposite-order owner grants, revoke-versus-consumer, credential/deactivation races and an already-open repeatable-read snapshot remain NOT_RUN for this revision until a captain-allocated isolated database turn. SQLite does not prove row locking.

## Permissions and denials

workspace.read: all twelve known workspace names.
scheduling.read: owner, superadmin, supervisor, receptionist, clinic_manager, dentist, clinical_staff.
scheduling.write: owner, superadmin, receptionist, clinic_manager.
finance.read: owner, superadmin, accountant, clinic_manager.
diagnostics.read_redacted: developer.
Everything else is denied, including clinical.sign, clinical.read, finance.approve and owner.metrics. A permitted domain name does not implement that module's projection or authorise unscoped records.
Owner workspace requires an existing owner account; developer requires tech_admin; dentist requires clinician plus current verified practitioner credentials. Unknown account/workspace roles, inactive account/clinic/organisation/branch, future/expired/revoked/version-mismatched memberships, reserved demo identity and synthetic clinic deny. Multiple memberships are never unioned.

## Actual route module, awaiting W01 inclusion

`backend/routes/tenancy.php` contains web/auth/active-account routes under `/tenancy/v1`:
GET workspaces; GET workspace; POST workspace with membership_id.
POST organisations with clinic_id/display_name.
POST organisations/{clinic}/branches with code/name.
POST organisations/{clinic}/memberships with branch_id/user_id/workspace_role/optional active_until.
DELETE organisations/{clinic}/memberships/{membership} with expected version.
The management routes additionally require existing per-session recent authentication. This is not new MFA activation.
Successful JSON responses use private,no-store. Scope mismatches deny404, action denials403, conflicts409, validation422 and stale session proof423. Existing invalid demo sessions are rejected and invalidated by the common middleware; the next request is an unauthenticated401.
W01 must add exactly `require __DIR__.'/tenancy.php';` to shared web.php after normal review. W02 does not edit that shared path. Tests load this actual module, never fake controllers, while integration is pending.

## Mutations, audit and recovery

TenancyService creates organisation/branch, assigns an existing user and revokes membership. Assignment repeat returns the existing identical active grant; an expired/revoked grant is never reactivated by replay. Revocation increments its version; stale versions409. Last active branch owner cannot be removed through this service.
AuditEvent is written in the same transaction as successful mutations with actor/action/resource/correlation/time and encrypted clinic/branch/version metadata. No names, credentials or medical narrative in audit context. Denial/read/export retention/integrity and broader lifecycle audit are separate open work.
Migration000100 is additive and precedes W03's030000 and W06's060000. Empty-table down is permitted; populated tables explicitly refuse destructive rollback. Source rollback retains new records and schema. W01 alone approves schema rollout with fresh backup and migration-specific recovery proof. A previous general restore is not this migration's acceptance.

## Evidence on this revision

PHP8.3.35, PHPUnit12.5.34, unchanged Composer lock72127e7d217d9a2e9672704a25e760450f528ca9a8bd264a1df8f4af39bf1b0b; synthetic SQLite, fake/no external transport.
Original new tests10/102. Resumed regression18tests/123assertions had7 expected failures (five missing-envelope behaviours and two actual200instead404 demo boundaries). A later test exposed unknown account-role allowance; the demo-session test initially expected403 after invalidation but the correct existing response was401, so only the test was corrected, not middleware.
Current focused suite: TenancyFoundationTest plus MembershipAssignmentRegressionTest, ClinicDashboardIsolationTest and SelfProfileProjectionTest:41tests/437assertions PASS, exit0. Pint10owned PHP files passed after formatting. Populated rollback refusal was exercised; it is not a full recovery or concurrency proof.
Full suite, MariaDB, independent security review, canonical route integration, browser/role UX, deployment and owner acceptance are OPEN.

## Remaining W02 requirements

Invitations/activation/reactivation/offboarding, account lifecycle integration and all job/file/search/export/cache consumers; personal profile binding and module workspaces; supervisor/team qualifications; explicit patient/guardian/delegate authority and merge provenance; sensitive read/export audit and retention. The presence of twelve role names is not twelve delivered dashboards. W04 owns auth internals; W05 clinical implementation; W06 finance; W07 shared UI; W08 notifications. No new live clinical, merchant or patient authority is activated here.
