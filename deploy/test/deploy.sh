#!/usr/bin/env bash
set -euo pipefail

: "${RELEASE_SHA:?RELEASE_SHA is required}"

PG_CONTAINER="marsotomasyon-postgres-1"
ROLE_FILE="$HOME/.marsotomasyon/postgresql-roles.env"
WEB_PORT="5080"
WEB_BIND="0.0.0.0"
COMPOSE_FILE="deploy/test/compose.yml"

test -f "$ROLE_FILE"
# shellcheck disable=SC1090
source "$ROLE_FILE"

: "${MARS_PG_DATABASE:?}"
: "${MARS_PG_MASTER_USER:?}"
: "${MARS_PG_MASTER_PASSWORD:?}"
: "${MARS_PG_APP_USER:?}"
: "${MARS_PG_APP_PASSWORD:?}"

mapfile -t PG_NETWORKS < <(
  docker inspect --format '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}' "$PG_CONTAINER" |
    sed '/^[[:space:]]*$/d'
)
test "${#PG_NETWORKS[@]}" -eq 1
TEST_NETWORK="${PG_NETWORKS[0]}"

API_IMAGE="mars-foundation-api:$RELEASE_SHA"
MIGRATOR_IMAGE="mars-foundation-migrator:$RELEASE_SHA"
WEB_IMAGE="mars-foundation-web:$RELEASE_SHA"

echo "DEPLOY_BUILD_API=START"
docker build --pull -f deploy/docker/api.Dockerfile -t "$API_IMAGE" .
echo "DEPLOY_BUILD_API=PASS"

echo "DEPLOY_BUILD_MIGRATOR=START"
docker build --pull -f deploy/docker/migrator.Dockerfile -t "$MIGRATOR_IMAGE" .
echo "DEPLOY_BUILD_MIGRATOR=PASS"

echo "DEPLOY_BUILD_WEB=START"
docker build --pull -f deploy/docker/web.Dockerfile -t "$WEB_IMAGE" .
echo "DEPLOY_BUILD_WEB=PASS"

MIGRATION_CS="Host=$PG_CONTAINER;Database=$MARS_PG_DATABASE;Username=$MARS_PG_MASTER_USER;Password=$MARS_PG_MASTER_PASSWORD;Timeout=5;Command Timeout=60;Include Error Detail=false"
RUNTIME_CS="Host=$PG_CONTAINER;Database=$MARS_PG_DATABASE;Username=$MARS_PG_APP_USER;Password=$MARS_PG_APP_PASSWORD;Timeout=5;Command Timeout=30;Include Error Detail=false"

echo "DEPLOY_MIGRATION=START"
docker run --rm \
  --network "$TEST_NETWORK" \
  --read-only \
  --tmpfs /tmp \
  --security-opt no-new-privileges:true \
  -e MARS_MIGRATION_CONNECTION_STRING="$MIGRATION_CS" \
  "$MIGRATOR_IMAGE" \
  database update \
  --project src/Mars.Infrastructure/Mars.Infrastructure.csproj \
  --startup-project src/Mars.Infrastructure/Mars.Infrastructure.csproj \
  --configuration Release \
  --no-build
echo "DEPLOY_MIGRATION=PASS"

docker exec -e PGPASSWORD="$MARS_PG_MASTER_PASSWORD" -i "$PG_CONTAINER" \
  psql -h 127.0.0.1 -U "$MARS_PG_MASTER_USER" -d "$MARS_PG_DATABASE" \
  -v ON_ERROR_STOP=1 -v app_role="$MARS_PG_APP_USER" <<'SQL'
SELECT format('GRANT USAGE ON SCHEMA foundation TO %I', :'app_role') \gexec
SELECT format('GRANT USAGE ON SCHEMA identity TO %I', :'app_role') \gexec
SELECT format('GRANT USAGE ON SCHEMA parties TO %I', :'app_role') \gexec
SELECT format('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA foundation TO %I', :'app_role') \gexec
SELECT format('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA identity TO %I', :'app_role') \gexec
SELECT format('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA parties TO %I', :'app_role') \gexec
SELECT format('GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA foundation TO %I', :'app_role') \gexec
SELECT format('GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA identity TO %I', :'app_role') \gexec
SELECT format('GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA parties TO %I', :'app_role') \gexec
SELECT format('GRANT SELECT ON TABLE public."__EFMigrationsHistory" TO %I', :'app_role') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE mars_master IN SCHEMA foundation GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO %I', :'app_role') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE mars_master IN SCHEMA identity GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO %I', :'app_role') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE mars_master IN SCHEMA parties GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO %I', :'app_role') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE mars_master IN SCHEMA foundation GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO %I', :'app_role') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE mars_master IN SCHEMA identity GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO %I', :'app_role') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE mars_master IN SCHEMA parties GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO %I', :'app_role') \gexec
SQL
echo "DEPLOY_RUNTIME_GRANTS=PASS"

export MARS_RELEASE_TAG="$RELEASE_SHA"
export MARS_RUNTIME_CONNECTION_STRING="$RUNTIME_CS"
export MARS_TEST_NETWORK="$TEST_NETWORK"
export MARS_WEB_PORT="$WEB_PORT"
export MARS_WEB_BIND="$WEB_BIND"

docker compose -f "$COMPOSE_FILE" config --quiet
echo "DEPLOY_COMPOSE_CONFIG=PASS"

docker compose -f "$COMPOSE_FILE" up -d --remove-orphans

for attempt in $(seq 1 40); do
  LIVE_STATUS="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 3 "http://127.0.0.1:$WEB_PORT/health/live" || true)"
  READY_STATUS="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 3 "http://127.0.0.1:$WEB_PORT/health/ready" || true)"
  if [ "$LIVE_STATUS" = "200" ] && [ "$READY_STATUS" = "200" ]; then
    echo "DEPLOY_HEALTH=PASS"
    break
  fi
  if [ "$attempt" -eq 40 ]; then
    echo "DEPLOY_HEALTH=FAIL|LIVE=$LIVE_STATUS|READY=$READY_STATUS"
    docker compose -f "$COMPOSE_FILE" ps
    docker compose -f "$COMPOSE_FILE" logs --no-color --tail=120 api web
    exit 1
  fi
  sleep 2
done

WEB_STATUS="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 3 "http://127.0.0.1:$WEB_PORT/")"
COMPONENTS_STATUS="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 3 "http://127.0.0.1:$WEB_PORT/components")"
PROOF_PAGE_STATUS="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 3 "http://127.0.0.1:$WEB_PORT/proof")"
PARTY_PAGE_STATUS="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 3 "http://127.0.0.1:$WEB_PORT/parties/new")"
PROTECTED_STATUS="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 3 "http://127.0.0.1:$WEB_PORT/api/v1/foundation/context")"
PROOF_PROTECTED_STATUS="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 3 -X POST "http://127.0.0.1:$WEB_PORT/api/v1/foundation/proof")"
PARTY_PROTECTED_STATUS="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 3 -X POST -H 'Content-Type: application/json' -d '{"partyCode":"SMOKE","kind":"PERSON","legalName":"Smoke"}' "http://127.0.0.1:$WEB_PORT/api/v1/parties")"
OPENAPI_STATUS="$(curl -sS -o /tmp/mars-foundation-openapi.json -w '%{http_code}' --max-time 3 "http://127.0.0.1:$WEB_PORT/openapi/v1.json")"

test "$WEB_STATUS" = "200"
test "$COMPONENTS_STATUS" = "200"
test "$PROOF_PAGE_STATUS" = "200"
test "$PARTY_PAGE_STATUS" = "200"
test "$PROTECTED_STATUS" = "401"
test "$PROOF_PROTECTED_STATUS" = "401"
test "$PARTY_PROTECTED_STATUS" = "401"
test "$OPENAPI_STATUS" = "200"
grep -F '/api/v1/foundation/context' /tmp/mars-foundation-openapi.json >/dev/null
grep -F '/api/v1/foundation/proof' /tmp/mars-foundation-openapi.json >/dev/null
grep -F '/api/v1/parties' /tmp/mars-foundation-openapi.json >/dev/null
rm -f /tmp/mars-foundation-openapi.json

MIGRATION_COUNT="$(docker exec -e PGPASSWORD="$MARS_PG_MASTER_PASSWORD" "$PG_CONTAINER" \
  psql -h 127.0.0.1 -U "$MARS_PG_MASTER_USER" -d "$MARS_PG_DATABASE" -Atqc \
  'select count(*) from public."__EFMigrationsHistory"')"

echo "DEPLOY_WEB_STATUS=$WEB_STATUS"
echo "DEPLOY_COMPONENTS_STATUS=$COMPONENTS_STATUS"
echo "DEPLOY_PROOF_PAGE_STATUS=$PROOF_PAGE_STATUS"
echo "DEPLOY_PARTY_PAGE_STATUS=$PARTY_PAGE_STATUS"
echo "DEPLOY_PROTECTED_STATUS=$PROTECTED_STATUS"
echo "DEPLOY_PROOF_PROTECTED_STATUS=$PROOF_PROTECTED_STATUS"
echo "DEPLOY_PARTY_PROTECTED_STATUS=$PARTY_PROTECTED_STATUS"
echo "DEPLOY_OPENAPI_STATUS=$OPENAPI_STATUS"
echo "DEPLOY_EF_MIGRATION_COUNT=$MIGRATION_COUNT"
echo "DEPLOY_RESULT=PASS"
