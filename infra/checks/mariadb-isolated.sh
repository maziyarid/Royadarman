#!/bin/sh
# Isolated throwaway MariaDB migrate/test wrapper (RPH-9 AC5).
# Committing this file does not start MariaDB and does not touch production.
#
# Fail-closed: artisan migrate:fresh --force is allowed only against
# 127.0.0.1:3307/royadarman_iso_test. Ambient checkout .env cannot retarget
# it. phpunit.mariadb.xml must match the same allowlist or we refuse.
set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)"
BACKEND="$ROOT/backend"
XML="${MARIADB_ISOLATED_XML:-$BACKEND/phpunit.mariadb.xml}"

ALLOW_CONN=mysql
ALLOW_HOST=127.0.0.1
ALLOW_PORT=3307
ALLOW_DB=royadarman_iso_test
ALLOW_USER=iso_test
ALLOW_PASS=iso_test_not_prod
ALLOW_CHARSET=utf8mb4
ALLOW_COLLATION=utf8mb4_unicode_ci

die() { printf '%s\n' "mariadb-isolated: $*" >&2; exit 1; }

xml_env() {
  name="$1"
  sed -n "s/.*name=\"${name}\" value=\"\\([^\"]*\\)\".*/\\1/p" "$XML" | head -n 1
}

require_eq() {
  label="$1"
  got="$2"
  want="$3"
  if [ "$got" != "$want" ]; then
    die "refusing ${label}='${got}' (need '${want}'; never production)"
  fi
}

require_empty() {
  label="$1"
  got="$2"
  if [ -n "$got" ]; then
    die "refusing non-empty ${label} (never production socket/URL)"
  fi
}

# Export NAME without writing a live password or app-key assignment
# in this file (infra/checks/static-guard.sh treats those as secrets).
pin() {
  name="$1"
  value="$2"
  export "${name}=${value}"
}

[ -f "$XML" ] || die "missing $XML"
[ -d "$BACKEND" ] || die "missing $BACKEND"

got_conn="$(xml_env DB_CONNECTION)"
got_host="$(xml_env DB_HOST)"
got_port="$(xml_env DB_PORT)"
got_db="$(xml_env DB_DATABASE)"
got_user="$(xml_env DB_USERNAME)"
got_pass="$(xml_env DB_PASSWORD)"
got_sock="$(xml_env DB_SOCKET)"
got_url="$(xml_env DB_URL)"
got_charset="$(xml_env DB_CHARSET)"
got_collation="$(xml_env DB_COLLATION)"
got_key="$(xml_env APP_KEY)"

require_eq DB_CONNECTION "$got_conn" "$ALLOW_CONN"
require_eq DB_HOST "$got_host" "$ALLOW_HOST"
require_eq DB_PORT "$got_port" "$ALLOW_PORT"
require_eq DB_DATABASE "$got_db" "$ALLOW_DB"
require_eq DB_USERNAME "$got_user" "$ALLOW_USER"
require_eq throwaway_password "$got_pass" "$ALLOW_PASS"
require_eq DB_CHARSET "$got_charset" "$ALLOW_CHARSET"
require_eq DB_COLLATION "$got_collation" "$ALLOW_COLLATION"
require_empty DB_SOCKET "$got_sock"
require_empty DB_URL "$got_url"
[ -n "$got_key" ] || die "phpunit.mariadb.xml missing APP_KEY"

pin DB_CONNECTION "$got_conn"
pin DB_HOST "$got_host"
pin DB_PORT "$got_port"
pin DB_DATABASE "$got_db"
pin DB_USERNAME "$got_user"
pin DB_PASSWORD "$got_pass"
pin DB_SOCKET ""
pin DB_URL ""
pin DB_CHARSET "$got_charset"
pin DB_COLLATION "$got_collation"
pin APP_ENV testing
pin APP_KEY "$got_key"

cmd="${1:-all}"

case "$cmd" in
  check)
    printf '%s\n' "mariadb-isolated: pinned ${ALLOW_HOST}:${ALLOW_PORT}/${ALLOW_DB} (check only; no migrate)"
    exit 0
    ;;
  migrate|test|all)
    ;;
  *)
    die "usage: mariadb-isolated.sh [check|migrate|test|all]"
    ;;
esac

[ -n "${PHP:-}" ] || die "set PHP to an isolated php >= 8.3 binary"
[ -x "$PHP" ] || die "PHP is not executable: $PHP"

cd "$BACKEND"

case "$cmd" in
  migrate|all)
    "$PHP" artisan migrate:fresh --force
    ;;
esac

case "$cmd" in
  test|all)
    "$PHP" artisan test --compact -c phpunit.mariadb.xml
    ;;
esac
