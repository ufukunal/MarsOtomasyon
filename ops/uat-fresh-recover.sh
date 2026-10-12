#!/usr/bin/env bash
# Recover only the failed, newly created mars-uat-fresh PostgreSQL bootstrap.
# Its first attempt stopped at entrypoint Permission denied, before any UAT
# company/user data or migrations existed. Never touches mars-uat-lab or PROD.
set -Eeuo pipefail
umask 077
die() { echo "BLOCKED: $*" >&2; exit 77; }
[[ "$EUID" != 0 ]] || die "run as UAT user, not root"
ROOT="$HOME/mars-uat-fresh-src"
ENV_FILE="$HOME/.config/mars/uat-fresh.env"
[[ -d "$ROOT" && ! -L "$ROOT" ]] || die "fresh source checkout missing"
[[ -f "$ROOT/compose.uat.fresh.yml" && -f "$ROOT/ops/uat-lab-postgres-init.sh" ]] || die "fresh compose/init script missing"
[[ -f "$ENV_FILE" && ! -L "$ENV_FILE" ]] || die "fresh environment file missing"
[[ "$(stat -c '%U:%a' "$ENV_FILE")" == "$(id -un):600" ]] || die "fresh env owner/mode incorrect"
[[ "$(sed -n 's/^MARS_FRESH_PORT=//p' "$ENV_FILE")" == 18082 ]] || die "not port 18082"
[[ "$(sed -n 's/^MARS_FRESH_TAILSCALE_IP=//p' "$ENV_FILE")" == 100.127.235.30 ]] || die "unexpected Tailscale IP"
[[ "$(sed -n 's/^MARS_UAT_SHA=//p' "$ENV_FILE")" =~ ^[0-9a-f]{40}$ ]] || die "invalid pinned image SHA"
PROJECT=mars-uat-fresh
PG=mars-uat-fresh-postgres-1
VK=mars-uat-fresh-valkey-1
VOLUME=mars-uat-fresh_fresh_pg
[[ "$(sudo docker inspect "$PG" --format '{{index .Config.Labels "com.docker.compose.project"}}:{{index .Config.Labels "com.docker.compose.service"}}:{{.State.Status}}:{{.State.ExitCode}}' 2>/dev/null)" == "$PROJECT:postgres:exited:1" ]] || die "fresh postgres container does not match failed first-boot state"
[[ "$(sudo docker inspect "$PG" --format '{{range .Mounts}}{{if eq .Destination "/var/lib/postgresql/data"}}{{.Name}}{{end}}{{end}}')" == "$VOLUME" ]] || die "fresh postgres mount mismatch"
[[ "$(sudo docker volume inspect "$VOLUME" --format '{{index .Labels "com.docker.compose.project"}}' 2>/dev/null)" == "$PROJECT" ]] || die "fresh PG volume ownership mismatch"
[[ "$(sudo docker inspect "$VK" --format '{{index .Config.Labels "com.docker.compose.project"}}:{{index .Config.Labels "com.docker.compose.service"}}:{{.State.Status}}' 2>/dev/null)" == "$PROJECT:valkey:running" ]] || die "fresh Valkey ownership/running mismatch"
[[ "$(sudo docker inspect "$VK" --format '{{range .Mounts}}{{if eq .Destination "/data"}}{{.Name}}{{end}}{{end}}')" == mars-uat-fresh_fresh_valkey ]] || die "fresh Valkey mount mismatch"
[[ -z "$(sudo docker ps -aq --filter name='^/mars-uat-fresh-web-1$')" ]] || die "fresh web already exists; refuse destructive recovery"
OTHER="$(sudo docker ps -a --filter label=com.docker.compose.project=mart-uat-fresh --format '{{.Names}}')"
[[ -z "$OTHER" ]] || die "unexpected legacy project name"
while IFS= read -r cid; do
  [[ -z "$cid" ]] && continue
  name="$(sudo docker inspect "$cid" --format '{{.Name}}')"
  [[ "$name" == "/$PG" || "$name" == "/$VK" ]] || die "unexpected container in fresh project: $name"
done < <(sudo docker ps -aq --filter label=com.docker.compose.project="$PROJECT")
sudo docker logs "$PG" 2>&1 | grep -Eq '/docker-entrypoint-initdb.d/10-uat-init.sh: Permission denied' || die "postgres does not exhibit known fresh-init permission failure"
[[ "$(sudo docker inspect "$PG" --format '{{.Config.Image}}')" == postgres:16 ]] || die "unexpected postgres image"

# Snapshot the original UAT identities; these MUST NOT change.
OLD_PG="$(sudo docker inspect mars-uat-lab-postgres-1 --format '{{.Id}}')"
OLD_VK="$(sudo docker inspect mars-uat-lab-valkey-1 --format '{{.Id}}')"
OLD_WEB="$(sudo docker inspect mars-uat-lab-web-1 --format '{{.Id}}')"
for spec in "postgres:$OLD_PG" "valkey:$OLD_VK" "web:$OLD_WEB"; do
  service="${spec%%:*}"
  cid="${spec#*:}"
  [[ "$(sudo docker inspect "$cid" --format '{{index .Config.Labels "com.docker.compose.project"}}:{{index .Config.Labels "com.docker.compose.service"}}')" == "mars-uat-lab:$service" ]] || die "prior UAT isolation mismatch: $service"
done

# Docker's postgres image switches to an unprivileged postgres user before it
# SOURCES the bind-mounted init .sh. Only this file becomes world-readable;
# it contains SQL definitions, NO passwords or other secrets.
chmod 0644 "$ROOT/ops/uat-lab-postgres-init.sh"
[[ -r "$ROOT/ops/uat-lab-postgres-init.sh" ]] || die "init script unreadable"
cd "$ROOT"
DC=(sudo docker compose --env-file "$ENV_FILE" -f compose.uat.fresh.yml)
"${DC[@]}" config --quiet

echo '=== Recreate ONLY failed fresh PostgreSQL bootstrap volume ==='
sudo docker rm "$PG" >/dev/null
sudo docker volume rm "$VOLUME" >/dev/null
"${DC[@]}" up --wait -d postgres

echo '=== Verify isolated fresh PostgreSQL initialization ==='
INIT="$("${DC[@]}" exec -T postgres psql -w -X -tA -v ON_ERROR_STOP=1 -U mars_uat_owner -d postgres -c "
SELECT CASE WHEN
  (SELECT count(*) FROM pg_database WHERE datname IN ('mars_uat_master', 'mars_uat_demo_2026')) = 2
  AND EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'mars_uat_app' AND rolcanlogin AND NOT rolsuper AND NOT rolcreatedb AND NOT rolcreaterole)
THEN 'PASS' ELSE 'FAIL' END;")"
[[ "$INIT" == PASS ]] || die "fresh DB initialization failed"

echo '=== Run migrations ONLY on the fresh Master database ==='
"${DC[@]}" run --rm -T --no-deps --entrypoint php web artisan migrate --database=master --path=database/migrations/master --force
COUNTS="$("${DC[@]}" exec -T postgres psql -w -X -tA -v ON_ERROR_STOP=1 -U mars_uat_owner -d mars_uat_master -c 'SELECT (SELECT count(*) FROM companies), (SELECT count(*) FROM users)')"
[[ "$COUNTS" == "0|0" ]] || die "fresh Master contains unexpected company/user data"

echo '=== Start fresh web, bound to the Tailscale host IP on port 18082 ==='
"${DC[@]}" up --wait -d web

PAGE="$(mktemp)"
trap 'rm -f "$PAGE"' EXIT
READY=0
for i in {1..12}; do
  if curl --noproxy '*' -fsS --max-time 12 "http://100.127.235.30:18082/kurulum" -o "$PAGE" 2>/dev/null \
      && grep -Fq 'Firma Kurulum Sihirbazı' "$PAGE" \
      && grep -Fq '</html>' "$PAGE"; then
    READY=1
    break
  fi
  sleep 2
done
[[ "$READY" == 1 ]] || die "new /kurulum not reachable with full HTML"
[[ "$(sudo docker inspect mars-uat-lab-postgres-1 --format '{{.Id}}')" == "$OLD_PG" ]] || die "old UAT PostgreSQL changed"
[[ "$(sudo docker inspect mars-uat-lab-valkey-1 --format '{{.Id}}')" == "$OLD_VK" ]] || die "old UAT Valkey changed"
[[ "$(sudo docker inspect mars-uat-lab-web-1 --format '{{.Id}}')" == "$OLD_WEB" ]] || die "old UAT web changed"

echo '=== Final state ==='
"${DC[@]}" ps
echo "PASS: fresh PG initialized; fresh Master users and companies are empty."
echo "PASS: prior UAT container identities unchanged."
echo "FRESH_URL=http://100.127.235.30:18082/kurulum"
echo "SETUP: prefix=mars_uat_demo year=2026; create NEW test administrator."
