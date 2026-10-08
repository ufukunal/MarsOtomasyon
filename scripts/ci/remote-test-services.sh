#!/usr/bin/env bash
# Called by self-hosted runners on mars-ci; only connects to permanent test services.
set -Eeuo pipefail
database="${1:?Required named test database}"
case "$database" in
  MarsProject_Master_Test_R1|MarsProject_Master_Test_R2|MarsProject_Master_Test_R3|MarsProject_Coverage_Test) ;;
  *) echo "Refusing unapproved database name: $database" >&2; exit 2 ;;
esac
host=100.127.235.30
ssh_options=(-o BatchMode=yes -o ConnectTimeout=7 -o PasswordAuthentication=no -o StrictHostKeyChecking=accept-new)
# Read ONLY the designated test credentials, through authenticated Tailnet SSH.
# GitHub masks both values before adding them to the per-job environment file.
credentials="$(timeout 20 ssh "${ssh_options[@]}" ufuk@"$host" 'cat /home/ufuk/.config/mars-test-infra/secrets.env')"
pgpass="$(sed -n 's/^MARS_PG_PASSWORD=//p' <<<"$credentials" | head -1)"
valkeypass="$(sed -n 's/^MARS_VALKEY_PASSWORD=//p' <<<"$credentials" | head -1)"
[[ "$pgpass" =~ ^[[:xdigit:]]{64}$ && "$valkeypass" =~ ^[[:xdigit:]]{64}$ ]] || { echo "Missing or malformed test credentials" >&2; exit 1; }
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
unset pgpass valkeypass credentials
echo "Persistent PostgreSQL ($database) and Valkey verified over Tailnet."
