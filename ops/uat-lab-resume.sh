#!/usr/bin/env bash
# Guarded UAT continuation: no database/volume deletion or PROD changes.
set -Eeuo pipefail
trap 'echo "UAT_RESUME_EXIT=$?"' EXIT
cd "$(dirname "$0")/.."
SHA="$(git rev-parse HEAD)"
test -z "$(git status --porcelain)"
ENV_FILE="$HOME/.config/mars/uat-lab.env"
test -f "$ENV_FILE" && test ! -L "$ENV_FILE"
test "$(stat -c '%a' "$ENV_FILE")" = 600
test "$(stat -c '%U' "$ENV_FILE")" = "$(id -un)"
PREV=178eeb672ff9a3026753d36803295884809da936
CURRENT="$(sed -n 's/^MARS_UAT_SHA=//p' "$ENV_FILE")"
if test "$CURRENT" != "$PREV" && test "$CURRENT" != "$SHA"; then
  echo 'BLOCKED: unexpected UAT secrets SHA' >&2
  exit 77
fi
PGID="$(sudo docker ps -q --filter name=^/mars-uat-lab-postgres-1$)"
test -n "$PGID"
test "$(sudo docker inspect "$PGID" --format '{{index .Config.Labels "com.docker.compose.project"}}')" = mars-uat-lab
test "$(sudo docker inspect "$PGID" --format '{{index .Config.Labels "com.docker.compose.service"}}')" = postgres
test "$(sudo docker inspect "$PGID" --format '{{range .Mounts}}{{if eq .Destination "/var/lib/postgresql/data"}}{{.Name}}{{end}}{{end}}')" = mars-uat-lab_mars_uat_lab_pg
SAFE="$(sudo docker exec -u postgres "$PGID" psql -w -X -tA -v ON_ERROR_STOP=1 -U mars_uat_owner -d postgres -c "
SELECT CASE WHEN
 (SELECT count(*) FROM pg_database WHERE datname IN ('mars_uat_master','mars_uat_demo_2026'))=2
 AND (SELECT count(*) FROM pg_database WHERE NOT datistemplate AND datname NOT IN ('postgres','mars_uat_master','mars_uat_demo_2026'))=0
 AND EXISTS (SELECT 1 FROM pg_roles WHERE rolname='mars_uat_app' AND rolcanlogin AND NOT rolsuper AND NOT rolcreatedb AND NOT rolcreaterole AND NOT rolbypassrls)
 AND NOT has_database_privilege('mars_uat_app','postgres','CONNECT')
THEN 'SAFE' ELSE 'BLOCKED' END;")"
test "$SAFE" = SAFE
echo 'PASS: isolated UAT PostgreSQL verified'
IMAGE="ghcr.io/ufukunal/marsotomasyon:uat-$SHA"
sudo docker pull "$IMAGE" >/dev/null
test "$(sudo docker image inspect "$IMAGE" --format '{{index .Config.Labels "org.opencontainers.image.revision"}}')" = "$SHA"
if test "$CURRENT" = "$PREV"; then
  sed -i "s/^MARS_UAT_SHA=$PREV$/MARS_UAT_SHA=$SHA/" "$ENV_FILE"
fi
test "$(stat -c '%a' "$ENV_FILE")" = 600
grep -Fxq "MARS_UAT_SHA=$SHA" "$ENV_FILE"
echo 'PASS: image verified, passwords unchanged'
DC=(sudo docker compose --env-file "$ENV_FILE" -f compose.uat.lab.yml --profile application)
"${DC[@]}" config --quiet
echo '=== UAT MASTER MIGRATION ==='
"${DC[@]}" run --rm -T --no-deps --entrypoint php web artisan migrate --database=master --path=database/migrations/master --force
echo 'PASS: UAT Master migration'
echo '=== UAT WEB START ==='
"${DC[@]}" up -d --no-deps web
WEBID="$("${DC[@]}" ps -q web)"
test -n "$WEBID"
WEBIP="$(sudo docker inspect "$WEBID" --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}')"
test -n "$WEBIP"
PAGE="$(mktemp)"
trap 'status=$?; rm -f "$PAGE"; echo "UAT_RESUME_EXIT=$status"' EXIT
for i in $(seq 1 25); do
  if curl --noproxy '*' -fsS --max-time 4 "http://$WEBIP:8080/up" >/dev/null 2>&1 &&
     curl --noproxy '*' -fsS --max-time 4 "http://$WEBIP:8080/kurulum" -o "$PAGE" 2>/dev/null &&
     grep -Eiq 'Firma Kurulum Sihirbaz|Adım' "$PAGE"; then
    echo 'PASS: UAT SETUP WIZARD READY'
    echo "UAT_WEB_BRIDGE=$WEBIP:8080"
    exit 0
  fi
  sleep 2
done
echo 'BLOCKED: UAT HTTP wizard verification failed' >&2
exit 77
