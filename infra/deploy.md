# Deploy and rollback

**Committing this file does not deploy, migrate, or roll back production.**

Aligns with [`backend/DEPLOYMENT.md`](../backend/DEPLOYMENT.md). PHP binary is
always `/usr/local/bin/ea-php83` (`$PHP` below). `backend/` is the artefact.

## Before any host change

- [ ] Encrypted database, application, and `public_html` backups exist, with SHA-256 recorded.
- [ ] `INTAKE_ENABLED=false` unless every activation gate in `backend/CHECKLIST.md` is closed.
- [ ] Secrets are already on the host `.env` — not in git.
- [ ] `SESSION_CONNECTION` shares the AuditEvent connection in production (preflight fail-closed).
- [ ] Additive migrations only in this release (no rename/drop of live columns).

## Deploy (operator, on the host)

```bash
PHP=/usr/local/bin/ea-php83
APP=/home/royadarman/apps/royadarman-backend
cd "$APP"

"$PHP" -v   # must be 8.3.x
"$PHP" "$(command -v composer)" install --no-dev --prefer-dist --optimize-autoloader

# Schema: additive expand only. Never migrate:fresh on this host.
"$PHP" artisan migrate --force

"$PHP" artisan royadarman:preflight   # must pass against production .env

# Provenance: set ROYADARMAN_RELEASE_COMMIT when there is no .git on the host.
"$PHP" artisan royadarman:release-identity --write

# Restart the already-installed unit. Installing the unit is a separate change.
sudo systemctl restart royadarman-queue.service
```

Warm caches only with the production `.env` loaded. Do **not** run
`php artisan test`, `migrate:fresh`, or `config:clear` against live merely to
tick a box.

## Rollback

If application code fails:

1. Restore the previous artefact under `$APP` and the previous `public_html`.
2. Restart `royadarman-queue.service`.
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
