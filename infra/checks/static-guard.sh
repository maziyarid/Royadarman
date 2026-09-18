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

# Files required for RPH-8 AC1–AC3.
need "README.md"
need "host.env.example"
need "systemd/royadarman-queue.service"
need "cron/royadarman"
need "clamav.md"
need "deploy.md"
need "checks/clean-checkout.md"

contains "README.md" "Committing or merging this directory does not mutate production."
contains "host.env.example" "/usr/local/bin/ea-php83"
contains "host.env.example" "QUEUE_NAMES=otp,scanning,notifications,maintenance"
contains "host.env.example" "/usr/bin/clamscan"
contains "systemd/royadarman-queue.service" "COMMITTING THIS FILE DOES NOT INSTALL OR RESTART THE UNIT."
contains "systemd/royadarman-queue.service" "/usr/local/bin/ea-php83"
contains "systemd/royadarman-queue.service" "--queue=otp,scanning,notifications,maintenance"
contains "systemd/royadarman-queue.service" "DB_QUEUE_RETRY_AFTER"
contains "cron/royadarman" "COMMITTING THIS FILE DOES NOT INSTALL CRON."
contains "cron/royadarman" "/usr/local/bin/ea-php83 artisan schedule:run"
contains "clamav.md" "Committing this file does not install ClamAV"
contains "clamav.md" "/usr/bin/clamscan"
contains "deploy.md" "Committing this file does not deploy"
contains "deploy.md" "artisan migrate --force"
contains "deploy.md" "royadarman:release-identity --write"
contains "deploy.md" "Keep additive schema in place"
contains "checks/clean-checkout.md" "migrate:fresh --force # throwaway schema only"

# Queue reservation boundary (Greptile P1):
# Worker --timeout must sit several seconds below Laravel's DB_QUEUE_RETRY_AFTER
# (documented default 90). Presence of the tokens alone is insufficient: equal
# values allow a second worker to claim a still-running job.
UNIT="$INFRA/systemd/royadarman-queue.service"
WORKER_TIMEOUT=""
if [ -f "$UNIT" ]; then
  # Extract the first --timeout=N from ExecStart (digits only).
  WORKER_TIMEOUT="$(sed -n 's/.*--timeout=\([0-9][0-9]*\).*/\1/p' "$UNIT" | head -n 1)"
fi
DOCUMENTED_RETRY_AFTER=90
MIN_MARGIN=5
if [ -z "$WORKER_TIMEOUT" ]; then
  bad "systemd/royadarman-queue.service missing parseable --timeout=N"
elif ! printf '%s' "$WORKER_TIMEOUT" | grep -Eq '^[0-9]+$'; then
  bad "systemd/royadarman-queue.service --timeout is not an integer: $WORKER_TIMEOUT"
else
  ok "parsed worker --timeout=$WORKER_TIMEOUT"
  # Require documented default retry_after mentioned in the unit comments.
  if ! grep -F -q "default 90" "$UNIT" && ! grep -F -q "default 90s" "$UNIT"; then
    bad "unit must document Laravel DB_QUEUE_RETRY_AFTER default 90 so operators know the floor"
  else
    ok "unit documents DB_QUEUE_RETRY_AFTER default 90"
  fi
  margin=$((DOCUMENTED_RETRY_AFTER - WORKER_TIMEOUT))
  if [ "$margin" -lt "$MIN_MARGIN" ]; then
    bad "worker --timeout=$WORKER_TIMEOUT must be at least ${MIN_MARGIN}s below documented retry_after=$DOCUMENTED_RETRY_AFTER (margin=$margin)"
  else
    ok "worker --timeout=$WORKER_TIMEOUT is ${margin}s below documented retry_after=$DOCUMENTED_RETRY_AFTER (min margin ${MIN_MARGIN}s)"
  fi
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
  bad "host command templates must use ea-php83, not bare php"
else
  ok "queue/cron/deploy templates do not call bare php artisan"
fi

if [ "$fail" -ne 0 ]; then
  say "static-guard: FAILED"
  exit 1
fi
say "static-guard: PASSED"
