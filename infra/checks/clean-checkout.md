# Verification procedure

Two regimes. Do not mix them. See `backend/DEPLOYMENT.md`.

**Committing this file does not run tests on the host and is not PHPUnit evidence.**

## A. Static (this repository, no PHP required)

From the repo root:

```bash
sh infra/checks/static-guard.sh
```

Must pass before a host operator copies templates. This checks placeholders,
the PHP 8.3 binary, queue names, ClamAV path, `queue-timing.conf` lockstep,
and the non-mutation contract.

## B. Clean-checkout (pre-deploy / CI) — needs PHP >= 8.3 + Composer

Throwaway database only (SQLite `:memory:` or a disposable MariaDB schema).
Never inherit a production config cache.

```bash
PHP=/usr/local/bin/ea-php83   # or any php >= 8.3 in CI
cd backend
"$PHP" -v
"$PHP" "$(command -v composer)" install
"$PHP" "$(command -v composer)" validate
"$PHP" artisan config:clear          # clean checkout only
"$PHP" artisan migrate:fresh --force # throwaway schema only
"$PHP" artisan test
"$PHP" vendor/bin/pint --test
"$PHP" artisan royadarman:preflight
```

RPH-5 still requires this evidence for `SessionInventoryTest`,
`StaffProvisioningTest`, and `PreflightTest`. This hour did not execute it.

## C. Live-production smoke (post-deploy, non-destructive)

- `GET /up` or `/fa/` → 200
- Unauthenticated API errors keep the envelope + `request_id`
- `/fa/`, `/ar/`, `/en/` `lang`/`dir`
- `"$PHP" artisan royadarman:preflight` against production `.env`
- confirm live `config('queue.connections.database.retry_after')` vs `infra/queue-timing.conf`
- queue unit + cron are running with `ea-php83`
- no HTTP 5xx spike; no new `failed_jobs`

Forbidden on live: `migrate:fresh`, `php artisan test`, `config:clear` merely
to satisfy this list.
