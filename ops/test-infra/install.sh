#!/usr/bin/env bash
# Privileged, idempotent installation for the dedicated TEST VM only.
set -Eeuo pipefail
if [[ ${EUID} -ne 0 ]]; then
  echo "This script requires authorized root execution. Not attempting privilege escalation." >&2
  exit 1
fi
. /etc/os-release
if [[ "${ID}" != ubuntu || "${VERSION_ID}" != 24.04 ]]; then
  echo "Expected Ubuntu 24.04; refusing to change an unverified host." >&2
  exit 1
fi
if [[ "$(hostname)" != ufukmarsprod ]] || ! ip -4 addr show tailscale0 | grep -q '100.127.235.30/32'; then
  echo "Incorrect target VM or Tailscale address; refusing installation." >&2
  exit 1
fi
getent passwd ufuk >/dev/null || { echo "Expected ufuk account absent." >&2; exit 1; }
echo "=== Install distro Docker, Compose V2 and PostgreSQL client ==="
export DEBIAN_FRONTEND=noninteractive
apt-get update
if ! apt-cache show docker-compose-v2 >/dev/null 2>&1; then
  apt-get install -y software-properties-common
  add-apt-repository -y universe
  apt-get update
fi
apt-get install -y docker.io docker-compose-v2 postgresql-client-16 openssl
systemctl enable --now docker
docker compose version
echo "=== Provision isolated long-lived TEST data and credentials ==="
install -d -m 0755 -o root -g root /opt/mars-test-infra
install -m 0644 -o root -g root "$(dirname "$0")/compose.yaml" /opt/mars-test-infra/compose.yaml
install -d -m 0700 -o ufuk -g ufuk /home/ufuk/.config/mars-test-infra
secrets=/home/ufuk/.config/mars-test-infra/secrets.env
if [[ ! -e "$secrets" ]]; then
  {
    printf 'MARS_BIND_IP=100.127.235.30\n'
    printf 'MARS_PG_PASSWORD=%s\n' "$(openssl rand -hex 32)"
    printf 'MARS_VALKEY_PASSWORD=%s\n' "$(openssl rand -hex 32)"
  } > "$secrets"
  chown ufuk:ufuk "$secrets"
  chmod 0600 "$secrets"
fi
# Do not rotate an existing database password during an idempotent rerun.
[[ -s "$secrets" ]] || { echo "Missing credentials" >&2; exit 1; }
cd /opt/mars-test-infra
docker compose --env-file "$secrets" -f compose.yaml config --quiet
docker compose --env-file "$secrets" -f compose.yaml up -d --wait
echo "=== Create isolated named test databases if absent ==="
for db in MarsProject_Master_Test_R1 MarsProject_Master_Test_R2 MarsProject_Master_Test_R3 MarsProject_Coverage_Test; do
  exists="$(docker exec mars-test-postgres psql -U mars_test -d postgres -Atqc "SELECT 1 FROM pg_database WHERE datname = '$db'")"
  if [[ "$exists" != 1 ]]; then
    docker exec mars-test-postgres psql -U mars_test -d postgres -v ON_ERROR_STOP=1 -c "CREATE DATABASE \"$db\""
  fi
done
echo "=== Verify authenticated services without printing passwords ==="
set -a
# shellcheck disable=SC1090
source "$secrets"
set +a
PGPASSWORD="$MARS_PG_PASSWORD" psql -w -h "$MARS_BIND_IP" -p 55432 -U mars_test -d MarsProject_Master_Test_R1 -Atqc 'SELECT 1' | grep -qx 1
docker exec -e REDISCLI_AUTH="$MARS_VALKEY_PASSWORD" mars-test-valkey valkey-cli ping | grep -qx PONG
unset MARS_PG_PASSWORD MARS_VALKEY_PASSWORD
echo "INSTALL_OK: permanent Docker PostgreSQL and Valkey healthy; credentials excluded from logs."
