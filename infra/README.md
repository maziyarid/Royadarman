# Royadarman host templates

**Committing or merging this directory does not mutate production.**
These files are version-controlled *intent*. They do not install systemd units,
edit crontab, restart queue workers, run migrations, touch ClamAV, or change
the live cPanel/Apache vhost. A host operator applies them only after review,
using `infra/deploy.md`.

Canonical runtime docs remain [`backend/DEPLOYMENT.md`](../backend/DEPLOYMENT.md)
and [`backend/ARCHITECTURE.md`](../backend/ARCHITECTURE.md). This tree does not
redesign the AlmaLinux 9 / cPanel / Apache host.

## What lives here

| Path | Purpose |
|---|---|
| `host.env.example` | Documented host paths and PHP 8.3 binary. Placeholders only. |
| `queue-timing.conf` | Non-secret source of truth for worker timeout vs `DB_QUEUE_RETRY_AFTER`. |
| `systemd/royadarman-queue.service` | Supervised database queue worker (`otp,scanning,notifications,maintenance`). |
| `cron/royadarman` | Minute scheduler: `artisan schedule:run` via `/usr/local/bin/ea-php83`. |
| `clamav.md` | Fail-closed ClamAV expectations for OPG quarantine. |
| `deploy.md` | Additive deploy, rollback, and release-identity procedure. |
| `checks/clean-checkout.md` | Staging/CI verification vs live smoke vs empty-step Actions (do not mix). |
| `checks/ci-empty-step.md` | How to tell a 1–4s empty-step red X from PHPUnit. Not a pass. |
| `checks/static-guard.sh` | Secret/placeholder/binary static check. Safe to run without PHP. |

## Non-negotiables

- PHP CLI, cron, and queue **must** use `/usr/local/bin/ea-php83`, never bare `php`.
- Secrets stay in the host `.env`. Never copy `APP_KEY`, phone-hash, SMS, or DB credentials into this tree.
- Intake stays fail-closed until `royadarman:preflight` passes on production `.env`.
- Owner has no clinical-document access. No autonomous diagnosis. No payments.
- `backend/` is the only deployable application.
- Queue worker `--timeout` must equal `WORKER_TIMEOUT` in `queue-timing.conf` and sit `SAFETY_MARGIN` seconds below the live `DB_QUEUE_RETRY_AFTER`.

## Apply vs commit

1. Review the templates against the live host (binary path, user, unit name).
2. Take encrypted backups (see `deploy.md`).
3. Copy/enable units and cron **on the host** as a separate, ticketed change.
4. Record `royadarman:release-identity --write` after the artefact is in place.

If a path here disagrees with a later live inspection, update the template in
a PR. Do not invent a second hosting architecture.
