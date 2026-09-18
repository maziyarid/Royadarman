# Deploy and rollback

**Committing this file does not deploy, migrate, or roll back production.**

Aligns with [`backend/DEPLOYMENT.md`](../backend/DEPLOYMENT.md). PHP binary is
always `/usr/local/bin/ea-php83` (`$PHP` below). `backend/` is the artefact.

## Before any host change

- [ ] Encrypted database, application, and `public_html` backups exist, with SHA-256 recorded.
- [ ] `INTAKE_ENABLED=false` unless every activation gate in `backend/CHECKLIST.md` is closed. Intake stays disabled for the whole deploy window.
- [ ] Secrets are already on the host `.env` — not in git.
- [ ] `SESSION_CONNECTION` shares the AuditEvent connection in production (preflight fail-closed).
- [ ] Additive migrations only in this release (no rename/drop of live columns).
- [ ] Host `.env` `DB_QUEUE_RETRY_AFTER` and `ROYADARMAN_QUEUE_WORKER_TIMEOUT` match [`queue-timing.conf`](queue-timing.conf) (90 / 85, margin 5). A live override such as `DB_QUEUE_RETRY_AFTER=85` is a double-claim hazard.
- [ ] Live installed `royadarman-queue.service` ExecStart matches the reviewed contract (see `infra/checks/live-unit-verify.md`). As of 2026-09-18 it does **not**: live `--timeout=120` exceeds live `retry_after=90`. **STOP** until a ticketed host unit change aligns it. Do not silently restart stale configuration.

## Deploy (operator, on the host)

```bash
PHP=/usr/local/bin/ea-php83
APP=/home/royadarman/apps/royadarman-backend
cd "$APP"

"$PHP" -v   # must be 8.3.x
"$PHP" "$(command -v composer)" install --no-dev --prefer-dist --optimize-autoloader

# Fail-fast config gate BEFORE schema change. Intake remains disabled.
# Preflight reads live Laravel config, including
# config('queue.connections.database.retry_after') vs
# config('royadarman.queue.worker_timeout_seconds'). It does not inspect systemd.
"$PHP" artisan royadarman:preflight   # must pass against production .env

# Schema: additive expand only. Never migrate:fresh on this host.
"$PHP" artisan migrate --force

"$PHP" artisan royadarman:preflight   # re-check after migration

# Confirm the LIVE reservation window, not the documented default, before workers restart.
"$PHP" artisan tinker --execute="echo config('queue.connections.database.retry_after');"
# Must be >= WORKER_TIMEOUT + SAFETY_MARGIN from infra/queue-timing.conf.

# Provenance: set ROYADARMAN_RELEASE_COMMIT when there is no .git on the host.
"$PHP" artisan royadarman:release-identity --write

# LIVE unit gate (read-only). Preflight does not inspect systemd.
# Require installed ExecStart PHP binary, --queue list, --sleep, --tries,
# --backoff, --timeout, and --max-time to match host.env.example +
# queue-timing.conf + the systemd template before any restart. See
# infra/checks/live-unit-verify.md.
systemctl cat royadarman-queue.service
systemctl show -p ExecStart royadarman-queue.service
# STOP if ExecStart PHP path != PHP_BIN, --queue list != QUEUE_NAMES, or
# --sleep/--tries/--backoff/--timeout/--max-time differ from the reviewed
# template, or live --timeout is not SAFETY_MARGIN below live retry_after.
# Apply the reviewed unit as a separate ticketed host change; do not silently
# restart stale configuration.

# Restart only after the live unit matches the reviewed contract.
# Installing/replacing the unit is a separate change.
sudo systemctl restart royadarman-queue.service
```

Warm caches only with the production `.env` loaded. Do **not** run
`php artisan test`, `migrate:fresh`, or `config:clear` against live merely to
tick a box. Keep `INTAKE_ENABLED=false` unless every CHECKLIST.md gate is closed.

## Rollback

If application code fails:

1. Restore the previous artefact under `$APP` and the previous `public_html`.
2. Restart `royadarman-queue.service` **only if** the restored unit still matches the reviewed contract; otherwise STOP and ticket the unit first.
3. Keep additive schema in place — old code must tolerate new columns.
4. Overwrite `storage/app/release-identity.json` for the restored SHA
   (`"$PHP" artisan royadarman:release-identity --write` after setting
   `ROYADARMAN_RELEASE_COMMIT` to the restored commit).
5. If a migration failed part-way: **stop**, preserve the snapshot, inspect.
   Never blindly drop a column or table that live rows already use. Repair
   forward with a new expand/contract migration.

## Release identity

`storage/app/release-identity.json` is operator-only. Do not expose it over HTTP.

```json
{
  "commit": "<40-char-sha>",
  "built_at": "<iso-8601>",
  "composer_lock_sha256": "<sha256 of composer.lock>",
  "composer_lock_modified_at": "<iso-8601>"
}
```
