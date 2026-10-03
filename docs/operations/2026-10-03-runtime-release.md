# Scheduler, queue and readiness delivery — 3 October 2026

Base: `a5d7df5fb348d1b6c22939aa0e6adfd24b524626` (PR51). Deployment state and
final Git identity are recorded in the release checkpoint, not inferred from
this source document. The dated runbook baseline has been updated for resumed
workers. No policy, clinical grant, migration, dependency or provider change.

## Behaviour and evidence boundaries

The readiness page binds count-only backlog evidence, published-policy coverage,
existing owner acknowledgements, release identity and actual execution evidence
from the scheduler and each existing queue: otp, scanning, notifications and
maintenance. Technical administrators can read it; only the existing owner
endpoint can change acknowledgements, with its recent-session gate retained.
Every acknowledgement uses its existing CSRF/route/boolean binding and audit.
The response is private and no-store. All supported locales have real labels,
accessible errors, section links and external responsive styling.

Queue probes are harmless database jobs, not provider sends. Evidence ages from
the original issue time; a late/replayed probe cannot turn stale work into a new
success. Recent means execution within 300 seconds, not a currently running
process, healthy scanner/provider, delivery to a real recipient or capacity for
clinical booking. Failed dispatch does not renew earlier evidence: historical
proof expires normally. Independent backlog/settings gates also remain active.
Missing, future, malformed, unreadable or disabled evidence cannot pass the new
runtime gate. Unknown counts never become zero in the view. UTC evidence and
acknowledgements are displayed as Gregorian timestamps explicitly in Tehran.

Private versioned records use bounded JSON, strict allowlists, atomic replacement,
0600 files, a 0700 directory and bounded locking outside webroot. Reserved jobs
and unrelated queue rows are not stolen or deleted. Control records and private
logs contain no patient/recipient/payload/secret projection. Source tests use only
synthetic data; live tests must never exercise providers or real accounts.

SQL and private files are separate stores, not an atomic distributed transaction.
Replacement keeps a bounded prior/next journal until actual committed job rows
are reconciled. Otherwise a rolled-back SQL replacement could leave the private
pointer naming the wrong row, orphaning a serialized probe during uninstall.
Recovery verifies exact queue/class/random identity; ambiguous or foreign rows
are preserved and block retirement. Nested transaction contexts are refused.
Failure-injection tests cover SQL rollback and committed-but-unpublished state;
they are not a power-loss, process-kill or filesystem-durability guarantee.

## Integration and rollback lifecycle

1. Compare all fresh baseline hashes in `2026-10-03-runtime-baseline.json` under
   the shared deployment lock. Apply the pinned reviewed release as cPanel user
   using `tools/deploy_source_release.py --baseline
   docs/operations/2026-10-03-runtime-baseline.json --source CHECKOUT --apply`.
   Source rollback includes restoring old paths and removing additions.
2. Probes are disabled by default. Activate only after source, cache, hash and
   guest HTTP checks pass. Reload the existing queue gracefully, verify its actual
   service/PID, then run `php artisan operations:heartbeat --activate` as cPanel
   user. The command only enables the private flag; the real minute scheduler
   subsequently issues probes. Do not fabricate execution from activation alone.
3. Observe scheduler and all four queue evidence after a real scheduled tick.
   Verify recency, private permissions and bounded probe ownership. No actual
   recipient or payload is required. Keep an unavailable component open.
4. Before uninstalling this accepted release, run
   `php artisan operations:heartbeat --retire` under the integration lock.
   Retirement disables production first and validates every tracked probe before
   transactional deletion. If a probe is reserved, retain its handler, let the
   existing worker finish and retry retirement. Never remove the new job class
   while reserved/pending owned work remains. If identity/recovery evidence is
   ambiguous, stop uninstalling and investigate; never clear an entire queue.
5. Restore only exact recorded source paths, clear cache and reload the queue
   gracefully after successful retirement. Private evidence may be retained for
   traceability but remains disabled; it does not grant clinical access. Any
   reserved/mismatched job or metadata recovery failure remains a rollback gate.

The stored release identity was observed to still name a September22 release
even though source was at PR51. After successful integration, back up its exact
bytes/mode privately, generate identity with the verified merged Git SHA using
the existing `royadarman:release-identity --write --path PRIVATE_TEMP` command,
validate the resulting SHA/fields, then atomically replace the live record at
0600 under the same lock. Never infer current Git identity from that old value.
Keep a before/after metadata hash record alongside the private source backup.
Its timestamp is when identity was written; it is not proof of CI/build execution.

## Executed verification

Local PHP8.3.6 and isolated VPS PHP8.3.33 each pass the full656 tests/34,317
assertions. Host backend Pint373 files passes; both existing DOM-adapter smoke
scripts pass. Independent backend review ran35 tests/393 assertions and found
no concrete blocker; the UI adds11 tests/101 assertions.

The initial broader MariaDB attempt timed out at180 seconds; it is not a pass.
Its private server/config/datadir were removed. A focused repeat of the three
changed feature test classes with a longer limit passes50 tests/510 assertions
on socket-only synthetic MariaDB (host job `job_42f2c6179665`,211.38s total).
It tests real queue push/reservation/handling, issued-time bounds, SQL rollback,
recovery, retirement, persisted redacted counters and owner/technical-admin
boundaries. The temporary database/config were removed again. No production DB
tests or migration ran. Alternate queue connection/table, broad concurrency,
actual process-kill/power loss and authenticated browser layout remain unproven.
Reactivation can retain historical proof within300s; it is not a fresh health
test. Release integration must observe genuinely new scheduled evidence.

## Remaining acceptance

Native Android and iOS, the twelve workspace/branch backends, capacity-safe clinic
scheduling, clinician report publication, financial rules and staff MFA policy
remain open. Authenticated production browser/mobile-layout and real provider
delivery evidence are separate. The September full-restore privilege obstacle
was safely resolved in the prior SQL/site rehearsal; authenticated patient/file
recovery and off-host failover are still not claimed. No global roadmap status
or completion percentage follows from passing this bounded slice.
