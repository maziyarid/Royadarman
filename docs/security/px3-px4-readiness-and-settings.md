# PX.3 launch readiness and PX.4 integration settings

Status: bounded draft implementation; independent local PHP/SQLite test execution is recorded below. Not merged or deployed. Base main f99210e05c1fef2e186d76e625164cc4ea101d8a. Sources: issue #15 packets 5, 6 and 7, reconciled with the repository and isolated synthetic runtime; no production data.

## PX.3 finding (from source)

`LaunchReadinessController::index` computes `ready` from configuration and manual acknowledgement gates only. `queue_timing` compares the configured `retry_after` with the documented worker timeout; it observes no process. `failedJobs` and `queuedJobs` are counted and passed to the view but are NOT part of any gate, so `ready` can be true while jobs have failed, the queue is backlogged, outbox events are stuck or deliveries failed. Two manual acknowledgements (`sms_delivery_confirmed`, `backup_restore_rehearsed`) carry no expiry and no evidence link; the runbook records that restore is not proven, so that acknowledgement is an owner assertion only.

## PX.3 change

- New `OperationalHealth` service: count-only, redacted signals (failed jobs, oldest unreserved job age, stale reserved jobs, pending and stuck outbox events, deliveries stuck in `sending`, deliveries failed in 24 h). State is `ok`, `degraded` or `unknown` (database unreachable).
- `LaunchReadinessController` gets an `operational_backlog` gate. `ready` now turns false on backlog evidence.
- `worker_liveness` is always `unobservable`. A backlog-free `ok` means "no evidence of trouble", not "worker running". A real liveness check needs a scheduler-written heartbeat (console schedule, command, config: shared files, handoff required).

## PX.4 finding (from source)

- `IntegrationSettings::value()` and `applyToRuntimeConfig()` swallow database and decryption errors and fall back to the environment with no log. A corrupted or re-keyed row therefore silently reverts to environment values.
- Consequences: an unreadable `sms_provider` row can change which provider sends OTP and notifications; an unreadable `sms_callback_secret` changes which secret verifies callbacks; an unreadable `intake_enabled` row reverts an operator's pause to whatever the environment says, which can silently re-open patient intake.
- `forDisplay()` shows such a row as `source = none`, which hides that a row exists and that the effective value is the environment's.
- First-boot and migration availability is a valid reason for the fallback and is preserved.

## PX.4 change

- Setting-read failures are recorded once per service instance as a warning carrying only the key and the exception class name (never a value or message).
- `diagnostics()` returns redacted key to status (`unreadable`, `invalid`) and a count. An unset key is not a problem.
- Proposed safety behaviour in this unmerged draft: `intake_enabled` fails closed on an unreadable row or unavailable settings store, both through runtime configuration and direct value reads. Missing rows retain the configured fallback. This is a behaviour decision for the owner to confirm; existing patients and staff are unaffected because intake only gates new patient acquisition.
- Other keys keep the environment fallback (availability), now visible through logs, `forDisplay()['problem']` and the new `integration_settings` readiness gate. Choosing to fail closed for `sms_provider` would stop OTP delivery, so it is left as an owner decision.

## Handoffs and unknowns

- Codex inspected the real readiness/settings Blade views and added gate/help/failure labels in fa/ar/en, a visible worker-liveness limitation and unreadable-override warnings. Owner/technical-admin access and denied patient/coordinator/clinician/clinic-representative/inactive/demo paths are exercised.
- Views may assume `forDisplay()` keys; the new `problem` key is additive.
- The isolated test migration schema supports the assumed jobs, failed_jobs, integration_settings, audit_events and session columns. This is SQLite evidence, not MariaDB/live-worker proof.

## Integration corrections

- Direct intake reads and settings-store outages now use the same safe closed value; logging-sink failures do not make first boot throw.
- Queue evidence honours configured database connections/tables. Other queue backends and live worker liveness remain outside this database-store snapshot.
- A sending delivery is stale according to its latest updated_at (created_at only when no update exists), so an active retry is not misclassified by its original creation date.
- Controller counters reuse the redacted snapshot; unavailable evidence is unknown, not a second failing query or a fabricated zero.
- Unreadable overrides remain clearable in the existing settings UI, with effective fallback source/value displayed where non-secret. Counts/statuses expose no payloads, recipients, ciphertext or exception messages.
- No MFA activation policy, scheduler, queue worker, schema, role, production setting or release was changed.

## Verification

Author supplied all tests as NOT RUN. Codex installed PHP 8.3.6 and existing locked dev dependencies in a disposable local checkout; no production environment, database or notification credentials were copied. Laravel is 13.29.0. SQLite :memory:, array session/cache and fake delivery follow phpunit.xml.

The first integrated focused run passed 45 tests / 155 assertions. Three intake regressions were rerun against the original IntegrationSettings from main: all three failed specifically with `true is false`; the changed source was restored afterwards. This proves the tests detect the original fallback defect. Formatting corrections were applied only to changed files. Final results are appended after execution.

The proposed intake-failure change must receive integration review before merge/activation. Existing-patient login remains covered by the OTP regression suite; no authority is widened. Worker liveness, real SMS delivery, MariaDB behaviour, complete restore proof and MFA A/B/C remain open gates. Native credential/device endpoints and both apps remain proposals rather than implemented delivery.

Final focused execution: 46 tests passed / 159 assertions. Full suite: 438 tests, 435 passed, 3 failures, 0 errors (see details below). GD was added to the disposable runtime after the initial five image-fixture environment errors; those errors no longer occur.

The remaining failures were independently reproduced after temporarily restoring all changed tracked application/view/language files from original main, then restoring this patch: DemoPanelAccessTest::test_demo_session_can_read_support_home_profile_and_dashboard_but_cannot_mutate (expected 403, got 200); PatientRequestPageTest::test_patient_can_review_new_request_page_but_cannot_submit_when_intake_disabled (old request-form marker expectation); PatientRequestPageTest::test_enabled_request_page_contains_fail_closed_consent_workflow (inline style remains). They are pre-existing source/test disagreements, not waived passes. Fix/review them separately; the whole suite is not green.

The readiness controller still depends on its primary application database for policies, staffing and manual acknowledgements; this slice only turns unavailable queue evidence into an explicit unknown gate, and does not promise a fully usable dashboard during a primary-database outage.

Attachment provenance: px-handoff-for-codex.zip SHA-256 `6428698a0d61e8ed25e27ec886f7a2e483b9860dfb147c662e84ca465f44110d`; px34-new-files.patch SHA-256 `b54cd1f7a9a75372d41b6d3c0309c01669ff4547d5032c0446a8cbc5e0ec7443`. The attached patch matched the copy in the archive. Original uploads are unchanged. The replacement and anchor script were applied only in this isolated worktree; neither is installed as an application tool.
