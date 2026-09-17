# Royadarman deployment and rollback

Version-controlled host templates (queue unit, cron, ClamAV, deploy/rollback
checklists) live in [`../infra/`](../infra/README.md). **Committing or merging
those files does not install units, edit crontab, restart workers, run
migrations, or change production.** Apply them only as a reviewed host change
using `infra/deploy.md`.

## Runtime requirement

The shared host exposes multiple PHP versions. Laravel 13 requires PHP >= 8.3,
while the default CLI `php` on the host may be 8.2 and will fail before Laravel
boots. **Always run Artisan, Composer, cron and queue commands with an explicit
PHP 8.3 binary**, not bare `php`.

Define the binary once per session and use it everywhere:

```bash
PHP=/usr/local/bin/ea-php83
"$PHP" -v   # must report 8.3.x
```

Before any deploy step, verify every command that will run on the host uses the
same PHP 8.3 binary:

- [ ] `"$PHP" -v` reports PHP 8.3.x
- [ ] Composer uses it: `"$PHP" $(which composer) install ...` (or `composer` is
      symlinked to the 8.3 binary)
- [ ] Cron entry invokes `"$PHP" artisan schedule:run`
- [ ] `royadarman-queue.service` `ExecStart` uses `/usr/local/bin/ea-php83`
- [ ] The web vhost is assigned PHP 8.3 in WHM/cPanel (FPM)

Do **not** assume the web-vhost PHP version equals the CLI PHP version; verify
both. The scheduler and queue service already use `/usr/local/bin/ea-php83`
correctly on the live host.

## Release gate

- [ ] `composer.lock` is present and `"$PHP" $(which composer) install --no-dev --prefer-dist --optimize-autoloader` succeeds.
- [ ] `.env` is not in the source archive; production uses `APP_ENV=production` and `APP_DEBUG=false`.
- [ ] `INTAKE_ENABLED=false` remains set until legal consent text, licensed clinical lead, SMS delivery, staffing, scanner, encrypted backup and restore rehearsal are approved.
- [ ] `ROYADARMAN_PHONE_HASH_KEY` is an independent non-empty secret and the SMS callback secret is configured.
- [ ] `"$PHP" artisan royadarman:preflight` passes (refuses empty APP_KEY / phone hash key, debug true, intake enabled without provider/scanner/retention, unsafe disks/queues).
- [ ] MariaDB uses InnoDB, `utf8mb4_unicode_ci`, UTC and strict SQL mode.
- [ ] `/home/royadarman/private_uploads/{quarantine,approved}` is outside `public_html`, mode `0750`, and not reachable over HTTP.
- [ ] ClamAV is installed, current, and a real clean/EICAR/timeout test passes before intake activation.
- [ ] Apache routes only through the Laravel front controller; `.env`, application files, storage and vendor are not web-accessible.
- [ ] Queue supervisors run `otp`, `scanning`, `notifications`, and `maintenance` queues with bounded retries using `/usr/local/bin/ea-php83`.
- [ ] Scheduler runs once per minute (via `/usr/local/bin/ea-php83`) and retention has an approved configured duration; no duration is invented by code.
- [ ] Encrypted application/database/file backups have a successful restore rehearsal.

## Verification: clean checkout vs. live production

Two distinct verification regimes exist. Do not mix them.

### Clean-checkout verification (pre-deployment, CI)

Run in a fresh checkout/build with an isolated test database (SQLite `:memory:`
or a throwaway MariaDB schema) and **no** inherited production configuration
cache. This is the primary comprehensive regression gate.

```bash
PHP=/usr/local/bin/ea-php83  # or php >= 8.3 in CI
"$PHP" $(which composer) install
"$PHP" $(which composer) validate
"$PHP" -v                    # >= 8.3
"$PHP" artisan config:clear   # only in the clean test environment
"$PHP" artisan migrate:fresh --force
"$PHP" artisan test
vendor/bin/pint --test
"$PHP" artisan route:list
"$PHP" artisan route:cache && "$PHP" artisan route:clear
"$PHP" artisan royadarman:preflight
# locale parity, JS syntax/build, browser smoke tests
```

### Live-production smoke verification (post-deployment)

Run only **non-destructive** checks against the live configured application.
Never run `config:clear`, `config:cache`, `migrate`, or `php artisan test`
against production merely to satisfy a checklist; PHPUnit against a cached
production configuration produces misleading results (the testing environment
from `phpunit.xml` does not safely replace a cached production config).

Safe post-deploy checks:

- [ ] `GET /up` (or `/fa/`) returns 200.
- [ ] `GET /api/v1/...` unauthenticated and validation paths return the standard
      error envelope with a `request_id` and the expected status.
- [ ] `/fa/`, `/ar/`, `/en/` render with correct `lang` and `dir`.
- [ ] `"$PHP" artisan royadarman:preflight` passes against the production `.env`.
- [ ] Queue workers and scheduler are running with `/usr/local/bin/ea-php83`.
- [ ] No HTTP 5xx spike; no new failed jobs in the `failed_jobs` table.

## Database-safe release

1. Take encrypted database, application and public-root backups and record hashes.
2. Put the application in maintenance mode only for the short schema switch if an online expand step is impossible.
3. Deploy additive code and additive migrations first. Do not rename or drop live columns in the same release that stops writing them.
4. Run `"$PHP" artisan migrate --force`, warm caches, start workers, then smoke-test with intake still disabled.
5. Remove maintenance mode and monitor HTTP 5xx, queue failures, scanner failures and authentication throttles.

## Rollback

If application code fails, restore the previous release/front controller and restart workers. Keep additive schema in place; old code must tolerate it. If a migration fails, stop at the failed migration, preserve the database snapshot and inspect the exact partial state. Never run a blind rollback that drops a column or table already used by live records. Repair forward with a new expand/contract migration. Destructive contraction happens only in a later release after old code and data use have been verified absent and a fresh restore-tested backup exists.

## Release traceability

The deployed directory is not required to be a Git working tree. There must be a
trustworthy way to answer "what exact source revision is running?".

For every release, after deploying the artefact and before opening intake:

```bash
PHP=/usr/local/bin/ea-php83

# If deploying from a Git checkout, the command reads the working-tree HEAD.
# If deploying from an artefact (no .git), export ROYADARMAN_RELEASE_COMMIT
# in the production .env to the exact deployed commit SHA first.
"$PHP" artisan royadarman:release-identity --write
```

This writes `storage/app/release-identity.json` containing:

- `commit` — the deployed Git SHA (from `ROYADARMAN_RELEASE_COMMIT`, or the
  working-tree HEAD when that env var is unset);
- `built_at` — the release build timestamp;
- `composer_lock_sha256` — the SHA-256 of the committed `composer.lock`, so a
  dependency drift between the artefact and the locked dependencies is detectable;
- `composer_lock_modified_at` — the lockfile modification time.

The manifest is the operator-facing release provenance record. Do **not** expose
it publicly; it is intended for incident response and audit only. To confirm the
running release during an incident:

```bash
cat /home/royadarman/apps/royadarman-backend/storage/app/release-identity.json
```

Rollback provenance: when restoring a previous release, overwrite the manifest
for that release so the file always describes the code actually serving traffic.
Keep release manifests alongside backups so each restore-tested backup is
linked to the exact revision it was taken from.
