# Privileged administrator assurance and recovery slice

Base main/live50cf02fbb48f48b53ce00ad55a6feaa65aad9599; coordinated claim on
GitHub issue15 comments5969972637/5969977989 and RPH-85 findingF-2026-10-03-07.
The existing owner/active-account/demo/target policy is retained. This slice does
not decide staff MFA enrolment or reactivate PR28's proposed global roles.

## Behaviour

Four administrator writes now use existing EnsureRecentAuthentication: creating
staff, updating their name/locale/role/active status, resetting MFA, and revoking
their sessions. Proof belongs to the requesting browser session. A recent shared
users.last_authenticated_at value cannot grant freshness to another session.
Missing, expired, future or malformed proof returns423 before mutation; users,
audit events and target sessions stay unchanged. Fresh owner actions still use
their existing audited controllers; non-owner/demo/self/reserved/patient-target
and last-real-owner checks remain effective. Read-only directory access is not
turned into a recent-auth requirement.

The real administrator page now uses external mirrored CSS and workspace.js
confirmations instead of CSP-blocked inline styles/onsubmit. MFA reset and device
revocation have explicit confirmation; create/edit errors link to actual fields
and reopen the affected controls. The `_staff_form` hint is presentation-only:
the controller never uses it to select or authorise a target. Role choices, four
route/form bindings, CSRF tokens and PATCH method are retained. Authenticator
status is labelled precisely; no all-MFA-on claim from the TOTP-only projection.
Recent-auth help uses existing sign-out/sign-in; no nonexistent reauth endpoint
is promised. Persian, Arabic and English copy is provided.

Deployment tooling now considers tracked routes and preflights every changed
source parent's writability as the cPanel user before backup/writes. It preserves
locking, live-hash drift checks, source-only atomic updates and rollback. Fresh
440-file baseline: docs/operations/2026-10-03-admin-assurance-baseline.json.

## Verification

Baseline regression: seven existing-path positive cases passed;28 invalid-proof
cases failed because users/audits/sessions were actually changed. After the
four route bindings, all35 recent-auth tests pass. Existing successful reset-MFA
characterisation fixtures now supply legitimate per-session assurance; their MFA
policy assertions are unchanged. UI baseline: five failures/two passes; new
seven render tests pass with75 assertions. Local PHP8.3.6 combined full suite:
610 tests/33,823 assertions, backend global Pint passes. Existing navigation and
calendar DOM-adapter smoke and deployment-script syntax checks pass.

VPS PHP8.3.33 isolated full SQLite suite:610 tests/33,823 assertions; backend
global Pint362 files. Private socket-only synthetic MariaDB57 tests/252 assertions
pass across administrator mutations/UI, existing MFA-reset characterisation and
per-session authentication. The temporary DB/config/process were removed.
No production mutation/provider exercise or native-app
result is inferred from synthetic tests. The cloud browser verifies the protected
profile redirects to the real fa/login and renders password/passkey entry; it
does not have an authenticated owner session. Admin/profile authenticated visual
and touch-width checks remain open.

## Fresh cPanel backup and actual isolated restore

Private cPanel-owned0700 directory:
/home/royadarman/royadarman-cpanel-backup-20261003T141834Z-before-admin-assurance.
Both files0600; SQL uses a consistent single-transaction snapshot of61 InnoDB
tables. Site/app archive excludes volatile storage/framework and storage/logs;
private uploads, actual application/configuration and dependencies are included.
No credential file remains after the dump. Both gzip integrity checks pass.

| File | SHA-256 |
| --- | --- |
| database.sql.gz | a7d608c85d8235eed037bd5f0d99da8039916db92a1479f34b7b539cbc982981 |
| site-and-app.tar.gz | 6280d8bd13e25e7478934088ed49dd0f90b65020105652b9f7090e1e87bfd2e5 |

Unlike the earlier privilege-blocked attempt, this backup was imported into a
separate physical MariaDB datadir/socket with networking disabled and0700 parent.
The production application DB user received no new privileges. SQL import passed:
61 tables/83 foreign keys reconstructed. All10,697 regular archive members were
restored and their bytes checked against archive hashes. Restored application
configuration was forced to the private socket, confirmed by SELECT@@socket;
guest login returned200 and protected panel302. Temporary DB/process/restored
files were removed. Redacted evidence stays in private restore-rehearsal.json.
The first metadata command failed before any dump due to SQL quoting; the
corrected backup/restore above is the observed successful evidence.

This proves the actual snapshot can restore its database/files and boot to guest
authentication. Authenticated patient/document recovery, off-host disaster
failover, recovery timing targets and a broad release approval remain separate.
No real patient records or secrets were published to GitHub/Agiflow.

## Deployment and remaining work

Reviewed checkout, cPanel UID1001, dry run then apply:

```
python3 tools/deploy_source_release.py --source CHECKOUT \
  --baseline docs/operations/2026-10-03-admin-assurance-baseline.json
```

Use `--apply` only after the dry run. No migration/seed/dependency replacement.
Source backup/release/live hashes and HTTP status results belong in checkpoint
comments. Existing staff-MFA reset/enrolment semantics, scoped twelve-workspace
roles, branch/booking capacity, clinical report publication, clinic finance and
Android/iOS devices/credentials/build/signing remain unfinished. Last-owner
concurrency and duplicate staff-create contention are existing hypotheses needing
dedicated production-family race tests; single-process guard preservation here
does not establish those invariants under concurrent requests.
