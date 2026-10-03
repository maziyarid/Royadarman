# 3 October integrated release

Owner authorised continued delivery, merging and backed-up direct-root deployment.
Codex's bounded integration claim is issue #15 comment 5969482464 and RPH-49
comment 01M40Y68CMHGMFCZEZPJJPHXG3. This document describes the integrated source;
publication, host execution and deployment evidence are recorded separately.

## Source reconciliation

Base main: dbcb114d83fdc1c3fdf7b8a1dc6f8a64977762dc.
All 400 scoped application and served-asset hashes matched the combination of
the deployed frontend branch, Grok 2 stack through 68c3f6e, and calendar PR24
b30ff98. The additional 28 public Studio files were captured byte-for-byte and
are now versioned. The capture manifest names its exclusions. It contains no
environment, database, private uploads, sessions, caches or dependency copies.
Studio is a sample-only static preview, not a clinical backend or native app.

Integrated pinned security branches: PR17 4730640; PR18 373b2d1; PR19 5659a50;
PR20 bdaa343; PR44 116cc47. PR28, PR45, PR46 and PR47 remain separate. The
published agent branches are not rewritten. PR24's calendar is the current
live algorithm; PR45/47 must reconcile with it before later integration.

## Behaviour

- Recent authentication belongs to the browser session. A different device's
  login cannot refresh an old session. Password/OTP/passkey events stamp the
  current session; logout clears the stamp. Existing sessions must log in again
  for protected actions. This does not enforce a new staff MFA enrolment policy.
- Rejected OTP and MFA verification attempts commit their counter instead of
  being rolled back with a validation exception.
- Operational readiness uses redacted queue/outbox/delivery evidence. Failed
  deliveries remain unresolved evidence regardless of age. Recent failure counts
  remain telemetry; a 24-hour boundary is not recovery. Worker liveness remains
  unobservable; no live-process or real-SMS proof is claimed.
- Integration overrides expose redacted corruption/invalid-value diagnostics.
  Unreadable intake configuration closes new intake. Missing optional settings
  retain normal fallback. Operators can replace corrupt overrides through the
  existing validated, encrypted and audited settings form without HTTP500.
- The patient request wizard uses external CSS and a native progress element.
  Paused intake shows the notice without a live form. Demo preview remains
  read-only; the real draft endpoint refuses its mutation.
- CI requests GD for existing synthetic image tests. Ten PHP files received
  formatting-only fixes needed by the existing global Pint check.

## Verification

Isolated PHP 8.3.6, locked dependencies, SQLite :memory:, synthetic data, disabled
external delivery: full PHPUnit passed 532 tests /33,169 assertions, no failures,
errors, warnings or skips. Global Pint passed. JS syntax and existing navigation/
calendar DOM-adapter smoke passed. These are not authenticated production browser
or MariaDB race results. The settings-repair regression reproduced HTTP500 before
the fix; the old-failed-delivery regression reproduced a false healthy state.

## Deployment and rollback

Use tools/deploy_source_release.py against this checkout with the capture manifest.
Dry-run first, then apply as the cPanel user. It acquires the shared deployment
lock, refuses live drift, backs up only changed source files outside webroot,
preserves existing modes, clears caches, checks public statuses, and records file
hashes. Failure restores those files and removes only newly deployed files.
No migration, database restore, provider send, production seed or queue restart.
Do not extract an entire older archive over vendor, storage or a later release.

## Open delivery work

RPH-49/57 keep broader release/restore acceptance open. The original full-database
restore rehearsal is not completed by a source-only rollback. RPH-58/96 still
need implemented tenant/branch/workspace persistence and authorised workflows.
RPH-60/98/99–109 still need real dedicated-role UI/backend bindings; the Studio
does not satisfy them. Clinical taxonomy, signing/retention and finance rules
remain decision gates. RPH-65/94 still need capacity-safe clinic booking and real
calendar/notification correlation. PR28/46 integration, PR47 and remaining review
findings require their own verification. Android/iOS source, builds, signing and
device tests remain undelivered. Staff MFA enrolment/recovery remains undecided.
