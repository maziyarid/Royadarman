# Isolated throwaway MariaDB lane (RPH-9 AC5)

**Committing this file does not start MariaDB, does not touch the
production VPS, and is not GitHub Actions evidence.**

Use this only on an isolated runner. Never against the live host
`mariadb.service`. A previous production-daemon OOM during RPH-5 is why
this lane exists.

Canonical SQLite evidence remains `backend/phpunit.xml`
(`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).

## What this is

A throwaway MariaDB schema that is reasonably representative of
production (MariaDB 10.11 LTS, `utf8mb4` / `utf8mb4_unicode_ci`,
strict SQL mode) so length and driver differences SQLite hides are
visible before merge.

| Item | Isolated contract |
|---|---|
| Daemon | Loopback only (`127.0.0.1`). Never production. |
| Port | `3307` (not `3306`) |
| Schema | `royadarman_iso_test` |
| User | `iso_test` / `iso_test_not_prod` (throwaway; same class as the phpunit APP_KEY) |
| PHPUnit config | `backend/phpunit.mariadb.xml` (`force=true` so a checkout `.env` cannot leak) |
| Wrapper | `infra/checks/mariadb-isolated.sh` (fail-closed pin; refuses any other target) |

## Runner (isolated machine with PHP >= 8.3 + pdo_mysql)

1. Start a **new** MariaDB datadir. Bind `127.0.0.1` only. Character set
   `utf8mb4`, collation `utf8mb4_unicode_ci`.
2. `CREATE DATABASE royadarman_iso_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
3. Grant only that schema to `iso_test`.
4. From the **repo root** (never a bare `artisan migrate:fresh` — that
   reads ambient `.env` and can rebuild the wrong database):

```bash
PHP=/usr/local/bin/ea-php83   # or any php >= 8.3 on the isolated runner
sh infra/checks/mariadb-isolated.sh check     # pin/allowlist only; no migrate
sh infra/checks/mariadb-isolated.sh           # migrate:fresh + phpunit, pinned
```

The wrapper loads `backend/phpunit.mariadb.xml`, requires it to match
`127.0.0.1` / `3307` / `royadarman_iso_test` / `iso_test`, exports those
values into the process (so a checkout `.env` cannot retarget
`migrate:fresh --force`), then runs artisan. Subcommands: `check`,
`migrate`, `test`, `all` (default).

5. `DROP DATABASE royadarman_iso_test;` and stop/destroy the throwaway
   datadir. Do not leave it running.

`migrate:fresh` and PHPUnit against this schema are forbidden on the
production VPS.

## What a passing isolated run is allowed to prove

- Empty-schema migrations apply on MariaDB utf8mb4.
- Encrypted columns that SQLite accepts as VARCHAR still fit on MariaDB
  (they must be TEXT).
- PHPUnit assertions that SQLite already passed.

It does **not** make `composer audit --locked` blocking in GitHub
Actions (AC3), and it does **not** turn an empty-step Actions red X into
a pass (AC2). See [`ci-empty-step.md`](ci-empty-step.md).
