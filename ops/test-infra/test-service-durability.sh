#!/usr/bin/env bash
# Maintenance-window only: controlled restarts of the dedicated TEST containers.
set -Eeuo pipefail
if [[ "${EUID}" -ne 0 || "$(hostname)" != ufukmarsprod ]] ||
   ! ip -4 addr show tailscale0 | grep -q '100.127.235.30/32'; then
  echo "Incorrect target host/privileges" >&2; exit 2
fi
# Never execute a maintenance script from a user-writable checkout as root.
[[ ! -L "$0" && "$(stat -c %u "$0")" == 0 ]] ||
  { echo "Install a root-owned maintenance script first" >&2; exit 2; }
# The credential file belongs to ufuk; parse its values as data, not shell commands.
secrets=/home/ufuk/.config/mars-test-infra/secrets.env
[[ -f "$secrets" && ! -L "$secrets" && -r "$secrets" ]] ||
  { echo "Missing or unsafe test-only credentials file" >&2; exit 2; }
read_config_value() {
  local key="$1"
  sed -n "s/^${key}=//p" "$secrets"
}
MARS_BIND_IP="$(read_config_value MARS_BIND_IP)"
MARS_PG_PASSWORD="$(read_config_value MARS_PG_PASSWORD)"
MARS_VALKEY_PASSWORD="$(read_config_value MARS_VALKEY_PASSWORD)"
[[ "$MARS_BIND_IP" == 100.127.235.30 ]] ||
  { echo "Unexpected test service bind address" >&2; exit 2; }
[[ "$MARS_PG_PASSWORD" =~ ^[a-fA-F0-9]{64}$ && "$MARS_VALKEY_PASSWORD" =~ ^[a-fA-F0-9]{64}$ ]] ||
  { echo "Invalid test-only credential format" >&2; exit 2; }
export PGPASSWORD="$MARS_PG_PASSWORD"
export REDISCLI_AUTH="$MARS_VALKEY_PASSWORD"
psqltest() {
  psql -w -X -v ON_ERROR_STOP=1 -h "$MARS_BIND_IP" -p 55432 -U mars_test -d postgres -Atqc "$1"
}
health() {
  local container="$1" i state
  for ((i=0;i<50;i++)); do
    state="$(docker inspect --format '{{.State.Health.Status}}' "$container" 2>/dev/null || true)"
    if [[ "$state" == healthy ]]; then return 0; fi
    sleep 2
  done
  echo "Timeout waiting for $container health: $state" >&2
  return 1
}
health mars-test-postgres
health mars-test-valkey
# Unique, logged PostgreSQL probe table in the isolated postgres maintenance DB.
suffix="$(openssl rand -hex 6)"
table="mars_ops_probe_$suffix"
key="mars:ops:durability:$suffix"
nonce="$(openssl rand -hex 16)"
cleanup() {
  psqltest "DROP TABLE IF EXISTS public.\"$table\"" >/dev/null 2>&1 || true
  docker exec -e REDISCLI_AUTH="$MARS_VALKEY_PASSWORD" mars-test-valkey valkey-cli -n 15 DEL "$key" >/dev/null 2>&1 || true
}
trap cleanup EXIT
psqltest "CREATE TABLE public.\"$table\" (nonce text NOT NULL); INSERT INTO public.\"$table\" (nonce) VALUES ('$nonce')" >/dev/null
docker exec -e REDISCLI_AUTH="$MARS_VALKEY_PASSWORD" mars-test-valkey valkey-cli -n 15 SET "$key" "$nonce" >/dev/null
echo '=== PostgreSQL container restart ==='
docker restart mars-test-postgres >/dev/null
health mars-test-postgres
[[ "$(psqltest "SELECT nonce FROM public.\"$table\" LIMIT 1")" == "$nonce" ]] ||
  { echo "PostgreSQL lost durability probe" >&2; exit 1; }
echo 'POSTGRES_CONTAINER_RESTART_AND_DATA_PASS'
echo '=== Valkey container restart ==='
sleep 3
docker restart mars-test-valkey >/dev/null
health mars-test-valkey
actual="$(docker exec -e REDISCLI_AUTH="$MARS_VALKEY_PASSWORD" mars-test-valkey valkey-cli -n 15 GET "$key" | tr -d '\r')"
[[ "$actual" == "$nonce" ]] || { echo "Valkey persistence check failed" >&2; exit 1; }
echo 'VALKEY_CONTAINER_RESTART_AND_AOF_PASS'
echo '=== Verify Docker restart policy, volumes and service ==='
[[ "$(docker inspect -f '{{.HostConfig.RestartPolicy.Name}}' mars-test-postgres)" == unless-stopped ]]
[[ "$(docker inspect -f '{{.HostConfig.RestartPolicy.Name}}' mars-test-valkey)" == unless-stopped ]]
docker volume inspect mars-test-services_postgres_data mars-test-services_valkey_data >/dev/null
systemctl is-enabled --quiet docker
systemctl is-active --quiet docker
echo 'CONTAINER_RESILIENCE_AND_VOLUME_CHECK_OK'
