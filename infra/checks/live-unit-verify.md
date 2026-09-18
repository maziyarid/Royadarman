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

## Observed live mismatch (2026-09-18, independent Grok MCP re-check)

**Committing these numbers does not change the installed unit.**
Read-only inspection of `royadarman-queue.service` as user `grok-royadarman`
(no sudo, no restart):

| Field | Reviewed contract | Live installed |
|---|---|---|
| PHP binary | `/usr/local/bin/ea-php83` | `/usr/local/bin/ea-php83` (match) |
| `--queue=` | `otp,scanning,notifications,maintenance` | `otp,scanning,notifications,maintenance,default` (extra `default`) |
| `--sleep=` | `1` | `3` |
| `--tries=` | `3` | `3` (match) |
| `--backoff=` | `5` | `10` |
| `--timeout=` | `85` | `120` |
| `--max-time=` | `3600` | `3600` (match) |
| live Laravel `retry_after` | `90` | `90` |
| unit state | — | active / enabled |

**STOP:** live `--timeout=120` exceeds live `retry_after=90`. That is the
stale-unit double-claim hazard. Do not restart. A separately controlled
host change must install the reviewed template before the next release restart.

Fragment path observed: `/etc/systemd/system/royadarman-queue.service`.
Live Laravel `retry_after` was read via `artisan tinker` config() only;
production `.env` was not printed.

## What this is not

- Not a substitute for `royadarman:preflight` (Laravel config).
- Not something static-guard can run (no host access from the repo check).
- Not permission to install or rewrite the unit from git alone.
- Not PHPUnit evidence.
- Not a production mutation.

See also: `infra/deploy.md` (restart gate), `infra/checks/clean-checkout.md` regime C.
