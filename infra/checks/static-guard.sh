#!/bin/sh
# Static contract for infra/ templates.
# Does not contact production, systemd, cron, MariaDB, or ClamAV.
set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)"
INFRA="$ROOT/infra"
fail=0

say() { printf '%s\n' "$*"; }
ok() { say "ok  $*"; }
bad() { say "FAIL $*"; fail=1; }

need() {
  path="$1"
  if [ ! -f "$INFRA/$path" ]; then
    bad "missing infra/$path"
  else
    ok "present infra/$path"
  fi
}

contains() {
  path="$1"
  needle="$2"
  if grep -F -q -- "$needle" "$INFRA/$path"; then
    ok "infra/$path contains: $needle"
  else
    bad "infra/$path missing: $needle"
  fi
}

# Parse KEY=integer from a conf/env file. Comments and blanks ignored.
conf_int() {
  file="$1"
  key="$2"
  if [ ! -f "$file" ]; then
    return 0
  fi
  sed -n "s/^${key}=\([0-9][0-9]*\)[[:space:]]*$/\1/p" "$file" | head -n 1
}

# Parse KEY=/absolute/path from a conf/env file. Comments and blanks ignored.
conf_path() {
  file="$1"
  key="$2"
  if [ ! -f "$file" ]; then
    return 0
  fi
  sed -n "s/^${key}=\(\/[^[:space:]#]*\)[[:space:]]*$/\1/p" "$file" | head -n 1
}

# First PHP=/absolute/path assignment in a markdown/shell file.
first_php_assign() {
  file="$1"
  if [ ! -f "$file" ]; then
    return 0
  fi
  sed -n 's/^[[:space:]]*PHP=\(\/[^[:space:]#]*\).*/\1/p' "$file" | head -n 1
}


# Files required for RPH-8 AC1–AC3.
need "README.md"
need "host.env.example"
need "queue-timing.conf"
need "systemd/royadarman-queue.service"
need "cron/royadarman"
need "clamav.md"
need "deploy.md"
need "checks/clean-checkout.md"
need "checks/ci-empty-step.md"
need "checks/live-unit-verify.md"

contains "README.md" "Committing or merging this directory does not mutate production."
contains "README.md" "queue-timing.conf"
contains "host.env.example" "QUEUE_NAMES=otp,scanning,notifications,maintenance"
contains "systemd/royadarman-queue.service" "COMMITTING THIS FILE DOES NOT INSTALL OR RESTART THE UNIT."
contains "systemd/royadarman-queue.service" "--queue=otp,scanning,notifications,maintenance"
contains "systemd/royadarman-queue.service" "infra/queue-timing.conf"
contains "cron/royadarman" "COMMITTING THIS FILE DOES NOT INSTALL CRON."
contains "cron/royadarman" "artisan schedule:run"
contains "clamav.md" "Committing this file does not install ClamAV"
contains "deploy.md" "Committing this file does not deploy"
contains "deploy.md" "artisan migrate --force"
contains "deploy.md" "royadarman:release-identity --write"
contains "deploy.md" "Keep additive schema in place"
contains "deploy.md" "INTAKE_ENABLED=false"
contains "deploy.md" "config('queue.connections.database.retry_after')"
contains "checks/clean-checkout.md" "migrate:fresh --force # throwaway schema only"
contains "checks/clean-checkout.md" '"$PHP" vendor/bin/pint --test'
contains "checks/ci-empty-step.md" "Committing this file does not enable GitHub Actions runners"
contains "checks/ci-empty-step.md" "Not an application-test failure"
contains "checks/ci-empty-step.md" "Not a pass"
contains "checks/live-unit-verify.md" "Committing this file does not inspect systemd"
contains "checks/live-unit-verify.md" "systemctl show -p ExecStart"
contains "checks/live-unit-verify.md" "Do not restart"
contains "deploy.md" "systemctl show -p ExecStart"
contains "deploy.md" "STOP if ExecStart"

# Queue reservation contract (ChatGPT REVIEW — b068639 / Greptile P1):
# Do not hard-code 90. Parse infra/queue-timing.conf, require the unit
# --timeout to equal declared WORKER_TIMEOUT, and require
# declared retry_after - worker timeout >= SAFETY_MARGIN.
CONTRACT="$INFRA/queue-timing.conf"
UNIT="$INFRA/systemd/royadarman-queue.service"
DECLARED_TIMEOUT="$(conf_int "$CONTRACT" WORKER_TIMEOUT)"
DECLARED_RETRY="$(conf_int "$CONTRACT" DB_QUEUE_RETRY_AFTER)"
DECLARED_MARGIN="$(conf_int "$CONTRACT" SAFETY_MARGIN)"

if [ -z "$DECLARED_TIMEOUT" ] || [ -z "$DECLARED_RETRY" ] || [ -z "$DECLARED_MARGIN" ]; then
  bad "queue-timing.conf must declare integer WORKER_TIMEOUT, DB_QUEUE_RETRY_AFTER, SAFETY_MARGIN"
else
  ok "parsed queue-timing.conf WORKER_TIMEOUT=$DECLARED_TIMEOUT DB_QUEUE_RETRY_AFTER=$DECLARED_RETRY SAFETY_MARGIN=$DECLARED_MARGIN"
  contract_margin=$((DECLARED_RETRY - DECLARED_TIMEOUT))
  if [ "$contract_margin" -lt "$DECLARED_MARGIN" ]; then
    bad "queue-timing.conf itself is unsafe: retry_after=$DECLARED_RETRY timeout=$DECLARED_TIMEOUT margin=$contract_margin (need >= $DECLARED_MARGIN)"
  else
    ok "queue-timing.conf margin ${contract_margin}s >= SAFETY_MARGIN ${DECLARED_MARGIN}s"
  fi
fi

WORKER_TIMEOUT=""
if [ -f "$UNIT" ]; then
  WORKER_TIMEOUT="$(sed -n 's/.*--timeout=\([0-9][0-9]*\).*/\1/p' "$UNIT" | head -n 1)"
fi
if [ -z "$WORKER_TIMEOUT" ]; then
  bad "systemd/royadarman-queue.service missing parseable --timeout=N"
elif ! printf '%s' "$WORKER_TIMEOUT" | grep -Eq '^[0-9]+$'; then
  bad "systemd/royadarman-queue.service --timeout is not an integer: $WORKER_TIMEOUT"
else
  ok "parsed worker --timeout=$WORKER_TIMEOUT"
  if [ -n "$DECLARED_TIMEOUT" ] && [ "$WORKER_TIMEOUT" -ne "$DECLARED_TIMEOUT" ]; then
    bad "unit --timeout=$WORKER_TIMEOUT must equal queue-timing.conf WORKER_TIMEOUT=$DECLARED_TIMEOUT"
  else
    ok "unit --timeout equals declared WORKER_TIMEOUT=$DECLARED_TIMEOUT"
  fi
  if [ -n "$DECLARED_RETRY" ] && [ -n "$DECLARED_MARGIN" ]; then
    margin=$((DECLARED_RETRY - WORKER_TIMEOUT))
    if [ "$margin" -lt "$DECLARED_MARGIN" ]; then
      bad "worker --timeout=$WORKER_TIMEOUT must be at least ${DECLARED_MARGIN}s below declared retry_after=$DECLARED_RETRY (margin=$margin)"
    else
      ok "worker --timeout=$WORKER_TIMEOUT is ${margin}s below declared retry_after=$DECLARED_RETRY (min margin ${DECLARED_MARGIN}s)"
    fi
  fi
fi

# host.env.example and backend/.env.example must lockstep with the contract.
HOST_RETRY="$(conf_int "$INFRA/host.env.example" DB_QUEUE_RETRY_AFTER)"
HOST_TIMEOUT="$(conf_int "$INFRA/host.env.example" ROYADARMAN_QUEUE_WORKER_TIMEOUT)"
ENV_RETRY="$(conf_int "$ROOT/backend/.env.example" DB_QUEUE_RETRY_AFTER)"
ENV_TIMEOUT="$(conf_int "$ROOT/backend/.env.example" ROYADARMAN_QUEUE_WORKER_TIMEOUT)"

if [ -n "$DECLARED_RETRY" ] && [ -n "$DECLARED_TIMEOUT" ]; then
  if [ "$HOST_RETRY" = "$DECLARED_RETRY" ] && [ "$HOST_TIMEOUT" = "$DECLARED_TIMEOUT" ]; then
    ok "host.env.example lockstep DB_QUEUE_RETRY_AFTER=$HOST_RETRY ROYADARMAN_QUEUE_WORKER_TIMEOUT=$HOST_TIMEOUT"
  else
    bad "host.env.example must set DB_QUEUE_RETRY_AFTER=$DECLARED_RETRY and ROYADARMAN_QUEUE_WORKER_TIMEOUT=$DECLARED_TIMEOUT (got retry=${HOST_RETRY:-missing} timeout=${HOST_TIMEOUT:-missing})"
  fi
  if [ "$ENV_RETRY" = "$DECLARED_RETRY" ] && [ "$ENV_TIMEOUT" = "$DECLARED_TIMEOUT" ]; then
    ok "backend/.env.example lockstep DB_QUEUE_RETRY_AFTER=$ENV_RETRY ROYADARMAN_QUEUE_WORKER_TIMEOUT=$ENV_TIMEOUT"
  else
    bad "backend/.env.example must set DB_QUEUE_RETRY_AFTER=$DECLARED_RETRY and ROYADARMAN_QUEUE_WORKER_TIMEOUT=$DECLARED_TIMEOUT (got retry=${ENV_RETRY:-missing} timeout=${ENV_TIMEOUT:-missing})"
  fi
fi

# Laravel config defaults must lockstep with the contract. A missing host .env
# falls back to these; drifting them re-introduces the magic-90 hole.
PHP_RETRY="$(sed -n "s/.*env('DB_QUEUE_RETRY_AFTER', *\\([0-9][0-9]*\\)).*/\\1/p" "$ROOT/backend/config/queue.php" | head -n 1)"
PHP_TIMEOUT="$(sed -n "s/.*env('ROYADARMAN_QUEUE_WORKER_TIMEOUT', *\\([0-9][0-9]*\\)).*/\\1/p" "$ROOT/backend/config/royadarman.php" | head -n 1)"
PHP_MARGIN="$(sed -n "s/.*'retry_after_min_margin_seconds' => *\\([0-9][0-9]*\\).*/\\1/p" "$ROOT/backend/config/royadarman.php" | head -n 1)"
if [ -n "$DECLARED_RETRY" ] && [ -n "$DECLARED_TIMEOUT" ] && [ -n "$DECLARED_MARGIN" ]; then
  if [ "$PHP_RETRY" = "$DECLARED_RETRY" ]; then
    ok "backend/config/queue.php env('DB_QUEUE_RETRY_AFTER') default=$PHP_RETRY"
  else
    bad "backend/config/queue.php env('DB_QUEUE_RETRY_AFTER') default must be $DECLARED_RETRY (got ${PHP_RETRY:-missing})"
  fi
  if [ "$PHP_TIMEOUT" = "$DECLARED_TIMEOUT" ]; then
    ok "backend/config/royadarman.php env('ROYADARMAN_QUEUE_WORKER_TIMEOUT') default=$PHP_TIMEOUT"
  else
    bad "backend/config/royadarman.php env('ROYADARMAN_QUEUE_WORKER_TIMEOUT') default must be $DECLARED_TIMEOUT (got ${PHP_TIMEOUT:-missing})"
  fi
  if [ "$PHP_MARGIN" = "$DECLARED_MARGIN" ]; then
    ok "backend/config/royadarman.php retry_after_min_margin_seconds=$PHP_MARGIN"
  else
    bad "backend/config/royadarman.php retry_after_min_margin_seconds must be $DECLARED_MARGIN (got ${PHP_MARGIN:-missing})"
  fi
fi

# Fail-closed live gate must remain in Preflight (does not inspect systemd).
PREFLIGHT="$ROOT/backend/app/Console/Commands/Preflight.php"
if [ -f "$PREFLIGHT" ] \
  && grep -F -q "config('queue.connections.database.retry_after')" "$PREFLIGHT" \
  && grep -F -q "config('royadarman.queue.worker_timeout_seconds')" "$PREFLIGHT" \
  && grep -F -q "config('royadarman.queue.retry_after_min_margin_seconds')" "$PREFLIGHT" \
  && grep -F -q 'DB_QUEUE_RETRY_AFTER' "$PREFLIGHT"; then
  ok "Preflight fail-closed queue-timing invariant is present"
else
  bad "Preflight.php must fail-closed on live retry_after vs worker timeout (see infra/queue-timing.conf)"
fi

# deploy.md must run preflight before migrate --force (fail-fast config gate).
preflight_line="$(grep -n 'artisan royadarman:preflight' "$INFRA/deploy.md" | head -n 1 | cut -d: -f1 || true)"
migrate_line="$(grep -n 'artisan migrate --force' "$INFRA/deploy.md" | head -n 1 | cut -d: -f1 || true)"
if [ -n "$preflight_line" ] && [ -n "$migrate_line" ] && [ "$preflight_line" -lt "$migrate_line" ]; then
  ok "deploy.md runs preflight (line $preflight_line) before migrate --force (line $migrate_line)"
else
  bad "deploy.md must run royadarman:preflight before migrate --force"
fi

# Pint must use the documented PHP 8.3 binary, not the host default CLI.
if grep -F -q '"$PHP" vendor/bin/pint --test' "$ROOT/backend/DEPLOYMENT.md"; then
  ok "backend/DEPLOYMENT.md invokes pint via \"\$PHP\""
else
  bad "backend/DEPLOYMENT.md must use \"\$PHP\" vendor/bin/pint --test"
fi

# PHP 8.3 binary contract (RPH-8 AC1): do not hard-code ea-php83.
# Parse PHP_BIN from host.env.example and require systemd/cron/deploy docs
# to use that exact absolute path. Bare `php` on this host may be 8.2.
PHP_BIN="$(conf_path "$INFRA/host.env.example" PHP_BIN)"
if [ -z "$PHP_BIN" ]; then
  bad "host.env.example must declare absolute PHP_BIN=/path"
elif ! printf '%s' "$PHP_BIN" | grep -Eq 'php83|php8\.3'; then
  bad "host.env.example PHP_BIN=$PHP_BIN must be a PHP 8.3 binary (path contains php83 or php8.3)"
else
  ok "parsed host.env.example PHP_BIN=$PHP_BIN (PHP 8.3)"
fi

UNIT_PHP=""
if [ -f "$UNIT" ]; then
  UNIT_PHP="$(sed -n 's/^ExecStart=\(\/[^[:space:]]*\)[[:space:]].*/\1/p' "$UNIT" | head -n 1)"
fi
CRON_FILE="$INFRA/cron/royadarman"
CRON_PHP=""
if [ -f "$CRON_FILE" ]; then
  CRON_PHP="$(grep -v '^[[:space:]]*#' "$CRON_FILE" | sed -n 's/.* && \(\/[^[:space:]]*\) artisan schedule:run.*/\1/p' | head -n 1)"
fi
DEPLOY_PHP="$(first_php_assign "$INFRA/deploy.md")"
DOC_PHP="$(first_php_assign "$ROOT/backend/DEPLOYMENT.md")"

if [ -n "$PHP_BIN" ]; then
  if [ "$UNIT_PHP" = "$PHP_BIN" ]; then
    ok "systemd ExecStart uses PHP_BIN=$PHP_BIN"
  else
    bad "systemd ExecStart must use host.env.example PHP_BIN=$PHP_BIN (got ${UNIT_PHP:-missing})"
  fi
  if [ "$CRON_PHP" = "$PHP_BIN" ]; then
    ok "cron schedule:run uses PHP_BIN=$PHP_BIN"
  else
    bad "cron/royadarman schedule:run must use host.env.example PHP_BIN=$PHP_BIN (got ${CRON_PHP:-missing})"
  fi
  if [ "$DEPLOY_PHP" = "$PHP_BIN" ]; then
    ok "deploy.md PHP= uses PHP_BIN=$PHP_BIN"
  else
    bad "deploy.md PHP= must use host.env.example PHP_BIN=$PHP_BIN (got ${DEPLOY_PHP:-missing})"
  fi
  if [ "$DOC_PHP" = "$PHP_BIN" ]; then
    ok "backend/DEPLOYMENT.md PHP= uses PHP_BIN=$PHP_BIN"
  else
    bad "backend/DEPLOYMENT.md PHP= must use host.env.example PHP_BIN=$PHP_BIN (got ${DOC_PHP:-missing})"
  fi
fi

# ClamAV command contract (RPH-8 AC1): do not hard-code /usr/bin/clamscan.
# Parse CLAMSCAN from host.env.example; Laravel default and .env.example
# must lockstep. Preflight still fail-closes on empty command when intake
# is on. This check does not inspect a live clamscan binary.
CLAMSCAN="$(conf_path "$INFRA/host.env.example" CLAMSCAN)"
ENV_CLAM="$(conf_path "$ROOT/backend/.env.example" ROYADARMAN_OPG_SCANNER_COMMAND)"
PHP_CLAM="$(sed -n "s/.*env('ROYADARMAN_OPG_SCANNER_COMMAND', *'\\([^']*\\)').*/\\1/p" "$ROOT/backend/config/royadarman.php" | head -n 1)"

if [ -z "$CLAMSCAN" ]; then
  bad "host.env.example must declare absolute CLAMSCAN=/path"
else
  ok "parsed host.env.example CLAMSCAN=$CLAMSCAN"
  if grep -F -q -- "$CLAMSCAN" "$INFRA/clamav.md"; then
    ok "clamav.md documents CLAMSCAN=$CLAMSCAN"
  else
    bad "clamav.md must document host.env.example CLAMSCAN=$CLAMSCAN"
  fi
  if [ "$ENV_CLAM" = "$CLAMSCAN" ]; then
    ok "backend/.env.example ROYADARMAN_OPG_SCANNER_COMMAND=$ENV_CLAM"
  else
    bad "backend/.env.example ROYADARMAN_OPG_SCANNER_COMMAND must be $CLAMSCAN (got ${ENV_CLAM:-missing})"
  fi
  if [ "$PHP_CLAM" = "$CLAMSCAN" ]; then
    ok "backend/config/royadarman.php scanner command default=$PHP_CLAM"
  else
    bad "backend/config/royadarman.php env('ROYADARMAN_OPG_SCANNER_COMMAND') default must be $CLAMSCAN (got ${PHP_CLAM:-missing})"
  fi
fi

if [ -f "$PREFLIGHT" ] \
  && grep -F -q "config('royadarman.opg.scanner.enabled')" "$PREFLIGHT" \
  && grep -F -q "config('royadarman.opg.scanner.command')" "$PREFLIGHT" \
  && grep -F -q 'scanner command is empty' "$PREFLIGHT"; then
  ok "Preflight fail-closed OPG scanner invariant is present"
else
  bad "Preflight.php must fail-closed when intake is on and the OPG scanner is disabled or empty"
fi


# Secret material must not appear as assigned values in templates.
# Placeholders, commented names, and this script's own regex are allowed.
secret_hits="$(grep -R -n -E \
  -I --exclude='static-guard.sh' --exclude-dir='.git' \
  'APP_KEY=base64:|BEGIN (RSA |OPENSSH )?PRIVATE KEY|TSMS_PASSWORD=[^[:space:]<#]|ROYADARMAN_PHONE_HASH_KEY=[0-9a-fA-F]{16,}|DB_PASSWORD=[^[:space:]<#]' \
  "$INFRA" || true)"
if [ -n "$secret_hits" ]; then
  say "$secret_hits"
  bad "infra/ appears to contain a live secret assignment"
else
  ok "no live secret assignments in infra/"
fi

# Bare php must not be the documented CLI for host commands.
if grep -n -E '(^|[[:space:]])php artisan|(^|[[:space:]])php -v' "$INFRA/systemd/royadarman-queue.service" "$INFRA/cron/royadarman" "$INFRA/deploy.md"; then
  bad "host command templates must use declared PHP_BIN, not bare php"
else
  ok "queue/cron/deploy templates do not call bare php artisan"
fi

if [ "$fail" -ne 0 ]; then
  say "static-guard: FAILED"
  exit 1
fi
say "static-guard: PASSED"
