# Live installed-unit verification (operator-only)

**Committing this file does not inspect systemd, restart workers, or change the host.**

Preflight and static-guard prove *repository* intent and *Laravel config*.
They cannot see the unit file that systemd actually runs. A stale installed
unit (for example `--timeout=120` while Laravel `retry_after` is 90)
passes Preflight and still recreates the double-claim boundary.

Use this procedure **on the host** before any `systemctl restart
royadarman-queue.service`. It is read-only until you decide the unit is
stale and open a separate ticketed host change.

## Required match

| Field | Must equal |
|---|---|
| PHP binary in `ExecStart` | `PHP_BIN` from `infra/host.env.example` (currently `/usr/local/bin/ea-php83`) |
| `--queue=` list | `QUEUE_NAMES` from `host.env.example` (`otp,scanning,notifications,maintenance`) — no extra `default` |
| `--sleep=` | template value (`1`) |
| `--tries=` | template value (`3`) |
| `--backoff=` | template value (`5`) |
| `--timeout=` | `WORKER_TIMEOUT` from `infra/queue-timing.conf` (currently `85`) |
| `--max-time=` | template value (`3600`) |

If any field differs, **STOP**. Do not restart. Apply the reviewed unit from
`infra/systemd/royadarman-queue.service` as a separate, ticketed host change
(`daemon-reload`, enable, then start). Do not silently restart stale config.

`--timeout` must stay `SAFETY_MARGIN` seconds below the *live*
`config('queue.connections.database.retry_after')`. If live timeout exceeds
live retry_after, that is the double-claim hazard. Do not restart.

## Operator commands (read-only)

```bash
# 1. Show the live unit text systemd will use
systemctl cat royadarman-queue.service

# 2. Parse the effective ExecStart (one line)
systemctl show -p ExecStart royadarman-queue.service

# 3. Manual checklist against the repo (run from a checkout or release artefact)
#    Compare PHP path, --queue list, --sleep, --tries, --backoff, --timeout,
#    and --max-time to host.env.example + queue-timing.conf + the systemd template.
```

Expected shape (values must match the current contract, not this example forever):

```text
ExecStart=/usr/local/bin/ea-php83 artisan queue:work database --queue=otp,scanning,notifications,maintenance --sleep=1 --tries=3 --backoff=5 --timeout=85 --max-time=3600
```

## Recording an observed mismatch

Record concrete live values, timestamps, host identity, and operator evidence in the
operations/task record for the specific incident or deployment attempt. Keep this
runbook contract-based: after the installed unit is corrected, historical bad values
must not become required repository invariants.

A live mismatch is evidence to **STOP**, not a new expected value. In particular, if
the installed worker timeout is greater than or too close to the live Laravel
`retry_after`, do not restart the worker until a separately reviewed host change
aligns the installed unit with the current repository contract.

## What this is not

- Not a substitute for `royadarman:preflight` (Laravel config).
- Not something static-guard can run (no host access from the repo check).
- Not permission to install or rewrite the unit from git alone.
- Not PHPUnit evidence.
- Not a production mutation.

See also: `infra/deploy.md` (restart gate), `infra/checks/clean-checkout.md` regime C.
