# Live installed-unit verification (operator-only)

**Committing this file does not inspect systemd, restart workers, or change the host.**

Preflight and static-guard prove *repository* intent and *Laravel config*.
They cannot see the unit file that systemd actually runs. A stale installed
unit (for example `--timeout=90` while the template and `.env` say 85/90)
passes Preflight and still recreates the double-claim boundary on restart.

Use this procedure **on the host** before any `systemctl restart
royadarman-queue.service`. It is read-only until you decide the unit is
stale and open a separate ticketed host change.

## Required match

| Field | Must equal |
|---|---|
| PHP binary in `ExecStart` | `PHP_BIN` from `infra/host.env.example` (currently `/usr/local/bin/ea-php83`) |
| `--queue=` list | `QUEUE_NAMES` from `host.env.example` (`otp,scanning,notifications,maintenance`) |
| `--timeout=` | `WORKER_TIMEOUT` from `infra/queue-timing.conf` (currently `85`) |

If any field differs, **STOP**. Do not restart. Apply the reviewed unit from
`infra/systemd/royadarman-queue.service` as a separate, ticketed host change
(`daemon-reload`, enable, then start). Do not silently restart stale config.

## Operator commands (read-only)

```bash
# 1. Show the live unit text systemd will use
systemctl cat royadarman-queue.service

# 2. Parse the effective ExecStart (one line)
systemctl show -p ExecStart royadarman-queue.service

# 3. Manual checklist against the repo (run from a checkout or release artefact)
#    Compare PHP path, --queue list, and --timeout=N to host.env.example + queue-timing.conf.
```

Expected shape (values must match the current contract, not this example forever):

```text
ExecStart=/usr/local/bin/ea-php83 artisan queue:work database --queue=otp,scanning,notifications,maintenance ... --timeout=85 ...
```

## What this is not

- Not a substitute for `royadarman:preflight` (Laravel config).
- Not something static-guard can run (no host access from the repo check).
- Not permission to install or rewrite the unit from git alone.
- Not PHPUnit evidence.

See also: `infra/deploy.md` (restart gate), `infra/checks/clean-checkout.md` regime C.
