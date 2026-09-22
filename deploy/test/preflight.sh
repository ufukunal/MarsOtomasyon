#!/usr/bin/env bash
set -euo pipefail

PG_CONTAINER="marsotomasyon-postgres-1"
VALKEY_CONTAINER="marsotomasyon-valkey-1"
ROLE_FILE="$HOME/.marsotomasyon/postgresql-roles.env"
PREFERRED_WEB_PORT="5080"

echo "PREFLIGHT_REMOTE_HOSTNAME=$(hostname)"
echo "PREFLIGHT_REMOTE_KERNEL=$(uname -r)"
if [ -r /etc/os-release ]; then
  . /etc/os-release
  echo "PREFLIGHT_REMOTE_OS=$PRETTY_NAME"
else
  echo "PREFLIGHT_REMOTE_OS=UNKNOWN"
fi

command -v docker >/dev/null
echo "PREFLIGHT_DOCKER=$(docker --version)"
docker compose version >/dev/null
echo "PREFLIGHT_COMPOSE=$(docker compose version --short)"

docker inspect "$PG_CONTAINER" >/dev/null
docker inspect "$VALKEY_CONTAINER" >/dev/null

echo "PREFLIGHT_CONTAINERS_BEGIN"
docker ps --format 'CONTAINER={{.Names}}|IMAGE={{.Image}}|STATUS={{.Status}}|PORTS={{.Ports}}'
echo "PREFLIGHT_CONTAINERS_END"

for container in "$PG_CONTAINER" "$VALKEY_CONTAINER"; do
  docker inspect --format     'COMPOSE_CONTAINER={{.Name}}|PROJECT={{index .Config.Labels "com.docker.compose.project"}}|SERVICE={{index .Config.Labels "com.docker.compose.service"}}|WORKDIR={{index .Config.Labels "com.docker.compose.project.working_dir"}}|CONFIG={{index .Config.Labels "com.docker.compose.project.config_files"}}'     "$container"
done

mapfile -t PG_NETWORKS < <(
  docker inspect --format '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}' "$PG_CONTAINER" |
    sed '/^[[:space:]]*$/d'
)

if [ "${#PG_NETWORKS[@]}" -ne 1 ]; then
  echo "PREFLIGHT_DATA_NETWORK=AMBIGUOUS_COUNT_${#PG_NETWORKS[@]}"
  exit 1
fi

TEST_NETWORK="${PG_NETWORKS[0]}"
docker network inspect "$TEST_NETWORK" >/dev/null
echo "PREFLIGHT_DATA_NETWORK=$TEST_NETWORK"

if [ -d /opt/marsotomasyon ]; then
  echo "PREFLIGHT_MARS_PATH=PRESENT"
  test -r /opt/marsotomasyon && echo "PREFLIGHT_MARS_PATH_READABLE=YES" || echo "PREFLIGHT_MARS_PATH_READABLE=NO"
  test -w /opt/marsotomasyon && echo "PREFLIGHT_MARS_PATH_WRITABLE=YES" || echo "PREFLIGHT_MARS_PATH_WRITABLE=NO"
  echo "PREFLIGHT_MARS_LAYOUT_BEGIN"
  find /opt/marsotomasyon -maxdepth 2 -mindepth 1 -type f \
    \( -name 'compose*.yml' -o -name 'compose*.yaml' -o -name 'docker-compose*.yml' -o -name 'docker-compose*.yaml' \) \
    -printf '%p\n' 2>/dev/null | sort || true
  echo "PREFLIGHT_MARS_LAYOUT_END"
else
  echo "PREFLIGHT_MARS_PATH=ABSENT"
fi

echo "PREFLIGHT_DISK_AVAILABLE_KB=$(df -Pk "$HOME" | awk 'NR==2 {print $4}')"

PORT_OWNER="$(docker ps --filter "publish=$PREFERRED_WEB_PORT" --format '{{.Names}}' | paste -sd, -)"
if [ -z "$PORT_OWNER" ]; then
  echo "PREFLIGHT_WEB_PORT_5080=FREE"
elif [[ "$PORT_OWNER" == mars-foundation-* ]]; then
  echo "PREFLIGHT_WEB_PORT_5080=OWNED_BY_MARS_FOUNDATION"
else
  echo "PREFLIGHT_WEB_PORT_5080=CONFLICT"
  exit 1
fi

if command -v ss >/dev/null; then
  ss -ltn | awk 'NR>1 {print $4}' | sed 's/.*://' | sort -n -u |
    awk 'BEGIN {printf "PREFLIGHT_LISTEN_PORTS="} {printf "%s%s", sep, $0; sep=","} END {print ""}'
else
  echo "PREFLIGHT_LISTEN_PORTS=UNKNOWN_NO_SS"
fi

TEST_HOST="mars-prod.taila20365.ts.net"
getent hosts "$TEST_HOST" >/dev/null
echo "PREFLIGHT_MAGIC_DNS=PASS"

if command -v curl >/dev/null; then
  HTTP_STATUS="$(curl -sS -o /dev/null -w '%{http_code}' --connect-timeout 2 --max-time 4 "http://$TEST_HOST/" || true)"
  HTTPS_STATUS="$(curl -k -sS -o /dev/null -w '%{http_code}' --connect-timeout 2 --max-time 4 "https://$TEST_HOST/" || true)"
  echo "PREFLIGHT_HTTP_80_STATUS=${HTTP_STATUS:-000}"
  echo "PREFLIGHT_HTTPS_443_STATUS=${HTTPS_STATUS:-000}"
else
  echo "PREFLIGHT_HTTP_80_STATUS=UNKNOWN_NO_CURL"
  echo "PREFLIGHT_HTTPS_443_STATUS=UNKNOWN_NO_CURL"
fi

if [ ! -f "$ROLE_FILE" ]; then
  echo "PREFLIGHT_POSTGRES_ROLE_FILE=MISSING"
  exit 1
fi

ROLE_MODE="$(stat -c '%a' "$ROLE_FILE")"
echo "PREFLIGHT_POSTGRES_ROLE_FILE=PRESENT"
echo "PREFLIGHT_POSTGRES_ROLE_FILE_MODE=$ROLE_MODE"
if [ "$ROLE_MODE" != "600" ]; then
  echo "PREFLIGHT_POSTGRES_ROLE_FILE_SECURITY=FAIL"
  exit 1
fi

# Never enable xtrace while role credentials are in scope.
# shellcheck disable=SC1090
source "$ROLE_FILE"
: "${MARS_PG_DATABASE:?}"
: "${MARS_PG_MASTER_USER:?}"
: "${MARS_PG_MASTER_PASSWORD:?}"
: "${MARS_PG_APP_USER:?}"
: "${MARS_PG_APP_PASSWORD:?}"

if docker exec -e PGPASSWORD="$MARS_PG_MASTER_PASSWORD" "$PG_CONTAINER" \
  psql -h 127.0.0.1 -U "$MARS_PG_MASTER_USER" -d "$MARS_PG_DATABASE" -Atqc 'select 1' |
  grep -qx '1'; then
  echo "PREFLIGHT_POSTGRES_MASTER_LOGIN=PASS"
else
  echo "PREFLIGHT_POSTGRES_MASTER_LOGIN=FAIL"
  exit 1
fi

if docker exec -e PGPASSWORD="$MARS_PG_APP_PASSWORD" "$PG_CONTAINER" \
  psql -h 127.0.0.1 -U "$MARS_PG_APP_USER" -d "$MARS_PG_DATABASE" -Atqc 'select 1' |
  grep -qx '1'; then
  echo "PREFLIGHT_POSTGRES_APP_LOGIN=PASS"
else
  echo "PREFLIGHT_POSTGRES_APP_LOGIN=FAIL"
  exit 1
fi

APP_FLAGS="$(docker exec -e PGPASSWORD="$MARS_PG_MASTER_PASSWORD" "$PG_CONTAINER" \
  psql -h 127.0.0.1 -U "$MARS_PG_MASTER_USER" -d "$MARS_PG_DATABASE" -Atqc \
  "select rolsuper::text || ',' || rolcreatedb::text || ',' || rolcreaterole::text || ',' || rolreplication::text from pg_roles where rolname = 'mars_app'")"
echo "PREFLIGHT_POSTGRES_APP_FLAGS=$APP_FLAGS"
if [ "$APP_FLAGS" != "false,false,false,false" ]; then
  echo "PREFLIGHT_POSTGRES_APP_PRIVILEGE_MODEL=FAIL"
  exit 1
fi
echo "PREFLIGHT_POSTGRES_APP_PRIVILEGE_MODEL=PASS"

if docker run --rm \
  --network "$TEST_NETWORK" \
  -e PGPASSWORD="$MARS_PG_MASTER_PASSWORD" \
  postgres:18-bookworm \
  psql -h "$PG_CONTAINER" -U "$MARS_PG_MASTER_USER" -d "$MARS_PG_DATABASE" -Atqc 'select 1' \
  2>/dev/null | grep -qx '1'; then
  echo "PREFLIGHT_POSTGRES_MASTER_NETWORK_LOGIN=PASS"
else
  echo "PREFLIGHT_POSTGRES_MASTER_NETWORK_LOGIN=FAIL"
  exit 1
fi

if docker run --rm \
  --network "$TEST_NETWORK" \
  -e PGPASSWORD="$MARS_PG_APP_PASSWORD" \
  postgres:18-bookworm \
  psql -h "$PG_CONTAINER" -U "$MARS_PG_APP_USER" -d "$MARS_PG_DATABASE" -Atqc 'select 1' \
  2>/dev/null | grep -qx '1'; then
  echo "PREFLIGHT_POSTGRES_APP_NETWORK_LOGIN=PASS"
else
  echo "PREFLIGHT_POSTGRES_APP_NETWORK_LOGIN=FAIL"
  exit 1
fi

MIGRATION_HISTORY_EXISTS="$(docker exec -e PGPASSWORD="$MARS_PG_MASTER_PASSWORD" "$PG_CONTAINER" \
  psql -h 127.0.0.1 -U "$MARS_PG_MASTER_USER" -d "$MARS_PG_DATABASE" -Atqc \
  "select case when to_regclass('public.\"__EFMigrationsHistory\"') is null then 'NO' else 'YES' end")"

if [ "$MIGRATION_HISTORY_EXISTS" = "YES" ]; then
  MIGRATION_COUNT="$(docker exec -e PGPASSWORD="$MARS_PG_MASTER_PASSWORD" "$PG_CONTAINER" \
    psql -h 127.0.0.1 -U "$MARS_PG_MASTER_USER" -d "$MARS_PG_DATABASE" -Atqc \
    'select count(*) from public."__EFMigrationsHistory"')"
else
  MIGRATION_COUNT=0
fi

echo "PREFLIGHT_EF_MIGRATION_HISTORY=$MIGRATION_HISTORY_EXISTS"
echo "PREFLIGHT_EF_MIGRATION_COUNT=$MIGRATION_COUNT"

FOUNDATION_SCHEMA="$(docker exec -e PGPASSWORD="$MARS_PG_MASTER_PASSWORD" "$PG_CONTAINER" \
  psql -h 127.0.0.1 -U "$MARS_PG_MASTER_USER" -d "$MARS_PG_DATABASE" -Atqc \
  "select case when to_regnamespace('foundation') is null then 'ABSENT' else 'PRESENT' end")"
IDENTITY_SCHEMA="$(docker exec -e PGPASSWORD="$MARS_PG_MASTER_PASSWORD" "$PG_CONTAINER" \
  psql -h 127.0.0.1 -U "$MARS_PG_MASTER_USER" -d "$MARS_PG_DATABASE" -Atqc \
  "select case when to_regnamespace('identity') is null then 'ABSENT' else 'PRESENT' end")"
echo "PREFLIGHT_FOUNDATION_SCHEMA=$FOUNDATION_SCHEMA"
echo "PREFLIGHT_IDENTITY_SCHEMA=$IDENTITY_SCHEMA"

echo "PREFLIGHT_WORKER_HOST=NOT_EXECUTABLE_CURRENT_SOURCE"
echo "PREFLIGHT_RESULT=PASS"
