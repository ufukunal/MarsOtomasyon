#!/usr/bin/env bash
# Fresh UAT Lab in separate Docker project, with ZERO reuse of old UAT/PROD volumes.
set -Eeuo pipefail
umask 077
SOURCE_REPO="${MARS_FRESH_SOURCE_REPO:-$HOME/mars-uat-129189}"
NEW_ROOT="$HOME/mars-uat-fresh-src"
ENV_FILE="$HOME/.config/mars/uat-fresh.env"
PROJECT=mars-uat-fresh
PORT=18082
EXPECTED_IP=100.127.235.30

stop() { echo "BLOCKED: $*" >&2; exit 77; }
[[ "$EUID" != 0 ]] || stop 'run as normal UAT user, not root'
for bin in git sudo docker openssl tar curl df awk tailscale ss; do
  command -v "$bin" >/dev/null || stop "missing $bin"
done
[[ -d "$SOURCE_REPO/.git" ]] || stop 'original UAT checkout not found'
[[ ! -e "$NEW_ROOT" && ! -L "$NEW_ROOT" ]] || stop 'fresh source directory already exists; do not overwrite'
[[ ! -e "$ENV_FILE" && ! -L "$ENV_FILE" ]] || stop 'fresh env already exists; do not overwrite'
[[ -z "$(sudo docker ps -aq --filter "label=com.docker.compose.project=$PROJECT")" ]] || stop 'fresh containers already exist'
for volume in fresh_pg fresh_valkey fresh_storage; do
  if sudo docker volume inspect "${PROJECT}_${volume}" >/dev/null 2>&1; then
    stop "fresh volume already exists: ${PROJECT}_${volume}"
  fi
done
if sudo docker network inspect "${PROJECT}_fresh_internal" >/dev/null 2>&1; then
  stop 'fresh network already exists'
fi
[[ -z "$(sudo ss -H -lnt "sport = :$PORT")" ]] || stop "port $PORT already occupied"
TAILIP="$(tailscale ip -4 | head -1)"
[[ "$TAILIP" == "$EXPECTED_IP" ]] || stop "unexpected Tailscale IP: $TAILIP"
MEM_KB="$(awk '/^MemAvailable:/ {print $2}' /proc/meminfo)"
[[ "$MEM_KB" =~ ^[0-9]+$ && "$MEM_KB" -ge 1600000 ]] || stop 'insufficient available RAM (need at least 1.6GB)'
DISK_KB="$(df -Pk "$HOME" | awk 'NR==2{print $4}')"
[[ "$DISK_KB" =~ ^[0-9]+$ && "$DISK_KB" -ge 2097152 ]] || stop 'insufficient free disk (need at least 2GB)'

OLD_WEB=mars-uat-lab-web-1
[[ "$(sudo docker inspect "$OLD_WEB" --format '{{index .Config.Labels "com.docker.compose.project"}}' 2>/dev/null)" == mars-uat-lab ]] || stop 'existing UAT lab not found'
RUNNING_SHA="$(sudo docker inspect "$OLD_WEB" --format '{{index .Config.Labels "org.opencontainers.image.revision"}}')"
[[ "$RUNNING_SHA" =~ ^[0-9a-f]{40}$ ]] || stop 'existing image has no immutable SHA'
IMAGE="ghcr.io/ufukunal/marsotomasyon:uat-$RUNNING_SHA"
[[ "$(sudo docker image inspect "$IMAGE" --format '{{index .Config.Labels "org.opencontainers.image.revision"}}' 2>/dev/null)" == "$RUNNING_SHA" ]] || stop 'pinned local image missing or incorrectly labeled'

cd "$SOURCE_REPO"
[[ -z "$(git status --porcelain)" ]] || stop 'existing UAT git checkout has local changes'
git fetch --prune origin main
HEAD_SHA="$(git rev-parse origin/main^{commit})"
git cat-file -e "$RUNNING_SHA^{commit}" || stop 'running image commit not in local git history'
# Fresh infrastructure may differ, but keep application code identical to running UAT.
if ! git diff --quiet "$RUNNING_SHA" "$HEAD_SHA" -- app resources routes config database composer.json composer.lock package.json package-lock.json Dockerfile.uat; then
  stop 'app source differs from the running UAT image; separate approved build required'
fi
for file in compose.uat.fresh.yml ops/uat-fresh-start.sh ops/uat-lab-postgres-init.sh; do
  git cat-file -e "$HEAD_SHA:$file" || stop "required source missing: $file"
done

mkdir -p "$NEW_ROOT" "$HOME/.config/mars"
git archive "$HEAD_SHA" | tar -x -C "$NEW_ROOT"
# The checkout is extracted with umask 077. PostgreSQL drops privileges to
# its postgres user before sourcing /docker-entrypoint-initdb.d/*.sh;
# allow that user to READ this single read-only bind-mounted init script.
chmod 0644 "$NEW_ROOT/ops/uat-lab-postgres-init.sh"
(
  umask 077
  {
    printf 'MARS_UAT_SHA=%s\n' "$RUNNING_SHA"
    printf 'MARS_FRESH_TAILSCALE_IP=%s\n' "$TAILIP"
    printf 'MARS_FRESH_PORT=%s\n' "$PORT"
    printf 'MARS_UAT_PG_OWNER_PASSWORD=%s\n' "$(openssl rand -hex 32)"
    printf 'MARS_UAT_PG_APP_PASSWORD=%s\n' "$(openssl rand -hex 32)"
    printf 'MARS_UAT_VALKEY_PASSWORD=%s\n' "$(openssl rand -hex 32)"
    printf 'MARS_UAT_APP_KEY=base64:%s\n' "$(openssl rand -base64 32 | tr -d '\n')"
  } > "$ENV_FILE"
)
[[ "$(stat -c '%a' "$ENV_FILE")" == 600 ]] || stop 'secret permissions invalid'
cd "$NEW_ROOT"
DC=(sudo docker compose --env-file "$ENV_FILE" -f compose.uat.fresh.yml)
"${DC[@]}" config --quiet

echo '=== FRESH: launch isolated PostgreSQL/Valkey ==='
"${DC[@]}" up --wait -d postgres valkey
echo '=== FRESH: migrate the NEW master DB ONLY ==='
"${DC[@]}" run --rm -T --no-deps --entrypoint php web artisan migrate --database=master --path=database/migrations/master --force
echo '=== FRESH: verify both company and user counts are zero ==='
COUNTS="$("${DC[@]}" exec -T postgres psql -w -X -A -t -v ON_ERROR_STOP=1 -U mars_uat_owner -d mars_uat_master -c 'SELECT (SELECT COUNT(*) FROM companies), (SELECT COUNT(*) FROM users)')"
[[ "$COUNTS" == '0|0' ]] || stop "new master unexpectedly contains data: $COUNTS"

echo '=== FRESH: launch web on Tailscale port 18082 ==='
"${DC[@]}" up --wait -d web
PAGE="$(mktemp)"
trap 'rm -f "$PAGE"' EXIT
READY=0
for i in $(seq 1 15); do
  if curl --noproxy '*' -fsS --max-time 12 "http://$TAILIP:$PORT/kurulum" -o "$PAGE" 2>/dev/null \
    && grep -Fq 'Firma Kurulum Sihirbazı' "$PAGE" \
    && grep -Fq '</html>' "$PAGE"; then
    READY=1
    break
  fi
  sleep 2
done
[[ "$READY" == 1 ]] || stop 'fresh /kurulum did not return COMPLETE HTML'
"${DC[@]}" ps
printf 'FRESH_IMAGE_SHA=%s\n' "$RUNNING_SHA"
printf 'FRESH_SOURCE_SNAPSHOT_SHA=%s\n' "$HEAD_SHA"
printf 'FRESH_MASTER_COUNTS=%s\n' "$COUNTS"
printf 'FRESH_URL=http://%s:%s/kurulum\n' "$TAILIP" "$PORT"
echo 'PASS: independent fresh UAT started; existing UAT and PROD remain unchanged.'
echo 'SETUP: company DB prefix mars_uat_demo, year 2026; use a new administrator.'
