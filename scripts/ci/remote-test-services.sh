#!/usr/bin/env bash
# Called by self-hosted runners on mars-ci; only connects to permanent test services.
set -Eeuo pipefail
database="${1:?Required named test database}"
case "$database" in
  MarsProject_Master_Test_R1|MarsProject_Master_Test_R2|MarsProject_Master_Test_R3|MarsProject_Coverage_Test) ;;
  *) echo "Refusing unapproved database name: $database" >&2; exit 2 ;;
esac
host=100.127.235.30
# The CI job injects pre-existing repository Actions secrets.
# Never fetch passwords over SSH, use alternate network paths, or print their values.
pgpass="${MARS_PG_PASSWORD:-}"
valkeypass="${MARS_VALKEY_PASSWORD:-}"
[[ "$pgpass" =~ ^[[:xdigit:]]{64}$ && "$valkeypass" =~ ^[[:xdigit:]]{64}$ ]] || {
  echo "CI_SECRET_MISSING_OR_INVALID: configure repository Actions secrets MARS_PG_PASSWORD and MARS_VALKEY_PASSWORD with existing test-only 64-hex values." >&2
  exit 3
}
echo "::add-mask::$pgpass"
echo "::add-mask::$valkeypass"
[[ -n "${GITHUB_ENV:-}" ]] || { echo "GITHUB_ENV unavailable" >&2; exit 1; }
{
  echo 'DB_HOST=100.127.235.30'
  echo 'DB_PORT=55432'
  echo 'DB_USERNAME=mars_test'
  echo "DB_PASSWORD=$pgpass"
  echo 'REDIS_HOST=100.127.235.30'
  echo 'REDIS_PORT=6379'
  echo "REDIS_PASSWORD=$valkeypass"
} >> "$GITHUB_ENV"
# Use an authenticated query: pg_isready alone does not validate credentials.
actual="$(PGPASSWORD="$pgpass" timeout 20 psql -w -h "$host" -p 55432 -U mars_test -d "$database" -Atqc 'SELECT current_database()')"
[[ "$actual" == "$database" ]] || { echo "Test DB connectivity failed" >&2; exit 1; }
# Valkey AUTH + PING using RESP on an authenticated Tailnet-only port.
exec 3<>"/dev/tcp/$host/6379"
printf '*2\r\n$4\r\nAUTH\r\n$64\r\n%s\r\n' "$valkeypass" >&3
IFS= read -r -t 8 response <&3
[[ "${response%$'\r'}" == '+OK' ]] || { echo "Valkey AUTH failed" >&2; exit 1; }
printf '*1\r\n$4\r\nPING\r\n' >&3
IFS= read -r -t 8 response <&3
[[ "${response%$'\r'}" == '+PONG' ]] || { echo "Valkey PING failed" >&2; exit 1; }
exec 3<&- 3>&-
unset pgpass valkeypass
echo "Persistent PostgreSQL ($database) and Valkey verified over Tailnet."
