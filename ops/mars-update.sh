#!/usr/bin/env bash
# Workflow-free, explicitly invoked UAT Lab web release.
# Never deploys PROD, never runs migrations, never removes data volumes.
set -Eeuo pipefail

if [[ "${1:-}" != update || "$#" -ne 1 ]]; then
  echo "Kullanim: bash ops/mars-update.sh update" >&2
  exit 2
fi
if (( EUID == 0 )); then
  echo "BLOCKED: Run as the ordinary UAT user, not root." >&2
  exit 77
fi
for bin in git sudo docker curl sed; do
  command -v "$bin" >/dev/null || { echo "BLOCKED: missing $bin" >&2; exit 77; }
done

REPO="${MARS_UAT_REPO:-$HOME/mars-uat-129189}"
[[ -d "$REPO/.git" && "$REPO" == /home/*/mars-uat-* ]] || {
  echo "BLOCKED: dedicated /home/<user>/mars-uat-* checkout required" >&2
  exit 77
}
cd "$REPO"
[[ -z "$(git status --porcelain)" ]] || {
  echo "BLOCKED: dirty checkout; nothing was changed" >&2
  exit 77
}
ENV_FILE="$HOME/.config/mars/uat-lab.env"
[[ -f "$ENV_FILE" && ! -L "$ENV_FILE" ]] || {
  echo "BLOCKED: dedicated UAT environment file missing" >&2
  exit 77
}
[[ "$(stat -c '%a' "$ENV_FILE")" == 600 && "$(stat -c '%U' "$ENV_FILE")" == "$(id -un)" ]] || {
  echo "BLOCKED: UAT environment ownership/mode invalid" >&2
  exit 77
}
OLD_SHA="$(sed -n 's/^MARS_UAT_SHA=//p' "$ENV_FILE")"
[[ "$OLD_SHA" =~ ^[0-9a-f]{40}$ ]] || {
  echo "BLOCKED: invalid UAT image pin" >&2
  exit 77
}
WEB_CID="$(sudo docker ps -q --filter name=^/mars-uat-lab-web-1$)"
PG_CID="$(sudo docker ps -q --filter name=^/mars-uat-lab-postgres-1$)"
VK_CID="$(sudo docker ps -q --filter name=^/mars-uat-lab-valkey-1$)"
[[ -n "$WEB_CID" && -n "$PG_CID" && -n "$VK_CID" ]] || {
  echo "BLOCKED: all three existing UAT Lab containers must be running" >&2
  exit 77
}
for spec in "web:$WEB_CID" "postgres:$PG_CID" "valkey:$VK_CID"; do
  service="${spec%%:*}"
  cid="${spec#*:}"
  [[ "$(sudo docker inspect "$cid" --format '{{index .Config.Labels "com.docker.compose.project"}}')" == mars-uat-lab
    && "$(sudo docker inspect "$cid" --format '{{index .Config.Labels "com.docker.compose.service"}}')" == "$service" ]] || {
    echo "BLOCKED: container ownership mismatch: $service" >&2
    exit 77
  }
done
[[ "$(sudo docker inspect "$PG_CID" --format '{{range .Mounts}}{{if eq .Destination "/var/lib/postgresql/data"}}{{.Name}}{{end}}{{end}}')" == mars-uat-lab_mars_uat_lab_pg ]] || {
  echo "BLOCKED: UAT PostgreSQL volume mismatch" >&2
  exit 77
}
[[ "$(sudo docker inspect "$VK_CID" --format '{{range .Mounts}}{{if eq .Destination "/data"}}{{.Name}}{{end}}{{end}}')" == mars-uat-lab_mars_uat_lab_valkey ]] || {
  echo "BLOCKED: UAT Valkey volume mismatch" >&2
  exit 77
}
[[ "$(sudo docker inspect "$WEB_CID" --format '{{index .Config.Labels "org.opencontainers.image.revision"}}')" == "$OLD_SHA" ]] || {
  echo "BLOCKED: running web revision differs from UAT release pin" >&2
  exit 77
}

echo "=== Fetch current main (no GitHub Actions required) ==="
git fetch --prune origin main
TARGET="$(git rev-parse refs/remotes/origin/main^{commit})"
git cat-file -e "$OLD_SHA^{commit}"
git merge-base --is-ancestor "$OLD_SHA" "$TARGET" || {
  echo "BLOCKED: current UAT release is not an ancestor of main" >&2
  exit 77
}
if [[ -n "$(git diff --name-only "$OLD_SHA" "$TARGET" -- database/migrations)" ]]; then
  echo "BLOCKED: database migration changes require a separate backed-up UAT migration plan" >&2
  exit 77
fi

# Keep a local tag pointing at the previous image for offline rollback.
REGISTRY="ghcr.io/ufukunal/marsotomasyon"
OLD_IMAGE="$REGISTRY:uat-$OLD_SHA"
NEW_IMAGE="$REGISTRY:uat-$TARGET"
sudo docker tag "$(sudo docker inspect "$WEB_CID" --format '{{.Image}}')" "$OLD_IMAGE"

START_HEAD="$(git rev-parse HEAD)"
SOURCE_SWITCHED=0
MARKER_CHANGED=0
SUCCESS=0
restore_on_failure() {
  rc="$?"
  if (( rc != 0 && SUCCESS == 0 )); then
    echo "FAILED: UAT update; attempting isolated web rollback" >&2
    if (( MARKER_CHANGED == 1 )); then
      sed -i "s/^MARS_UAT_SHA=$TARGET$/MARS_UAT_SHA=$OLD_SHA/" "$ENV_FILE" || true
      sudo docker compose --env-file "$ENV_FILE" -f compose.uat.lab.yml --profile application \
        up -d --no-deps --pull never --force-recreate web || true
    fi
    if (( SOURCE_SWITCHED == 1 )); then
      git switch --detach "$START_HEAD" >/dev/null || true
    fi
    echo "Rollback attempted; check the web revision before using UAT." >&2
  fi
}
trap restore_on_failure EXIT

echo "=== Build immutable UAT web image for $TARGET ==="
git switch --detach "$TARGET"
SOURCE_SWITCHED=1
[[ -z "$(git status --porcelain)" ]] || {
  echo "BLOCKED: checkout is not clean" >&2
  exit 77
}
sudo docker build --file Dockerfile.uat \
  --label "org.opencontainers.image.revision=$TARGET" \
  --tag "$NEW_IMAGE" .
[[ "$(sudo docker image inspect "$NEW_IMAGE" --format '{{index .Config.Labels "org.opencontainers.image.revision"}}')" == "$TARGET" ]]
sudo docker run --rm --entrypoint sh "$NEW_IMAGE" -ec '
  test -s /var/www/html/public/build/manifest.json
  grep -q "v65-home-workbench" /var/www/html/resources/views/livewire/reporting/dashboard.blade.php
  grep -q "v65-home-quick-grid" /var/www/html/resources/css/v65-shell.css
  grep -q "Dönem seçin" /var/www/html/resources/views/livewire/components/period-switcher.blade.php
'
echo "PASS: application templates and compiled frontend present in SHA-pinned image"

# Change only the release pin, not passwords or data service configuration.
if [[ "$OLD_SHA" != "$TARGET" ]]; then
  sed -i "s/^MARS_UAT_SHA=$OLD_SHA$/MARS_UAT_SHA=$TARGET/" "$ENV_FILE"
  MARKER_CHANGED=1
fi
[[ "$(stat -c '%a' "$ENV_FILE")" == 600 ]]
grep -Fxq "MARS_UAT_SHA=$TARGET" "$ENV_FILE"
DC=(sudo docker compose --env-file "$ENV_FILE" -f compose.uat.lab.yml --profile application)
"${DC[@]}" config --quiet
echo "=== Recreate UAT web only (no migration, DB stop or volume operation) ==="
"${DC[@]}" up -d --no-deps --pull never --force-recreate web
NEW_CID="$(sudo docker ps -q --filter name=^/mars-uat-lab-web-1$)"
[[ -n "$NEW_CID" ]]
[[ "$(sudo docker inspect "$NEW_CID" --format '{{index .Config.Labels "org.opencontainers.image.revision"}}')" == "$TARGET" ]]

# Compiled Blade views live on the UAT-only storage volume; discard stale views.
sudo docker exec "$NEW_CID" php artisan view:clear

WEB_IP="$(sudo docker inspect "$NEW_CID" --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}')"
[[ -n "$WEB_IP" ]]
READY=0
for i in $(seq 1 30); do
  if curl --noproxy '*' -fsS --max-time 4 "http://$WEB_IP:8080/up" >/dev/null 2>&1 \
    && curl --noproxy '*' -fsS --max-time 4 "http://$WEB_IP:8080/giris" >/dev/null 2>&1; then
    READY=1
    break
  fi
  sleep 2
done
[[ "$READY" == 1 ]] || {
  echo "FAILED: UAT web health/login not ready after release" >&2
  exit 1
}
SUCCESS=1
echo "PASS: UAT web running SHA=$TARGET"
echo "PASS: isolated PostgreSQL and Valkey containers were not recreated"
echo "NOTE: logged-in dashboard, company/period selection and external Tailscale bridge require browser verification"
