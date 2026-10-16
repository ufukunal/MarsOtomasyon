#!/usr/bin/env bash
# Fresh UAT ONLY: promote immutable image already published by CI, rollback on failure.
# Run as ufuk on PROD host. Does NOT build images or invoke database migrations.
set -Eeuo pipefail
umask 077
fail(){ echo "BLOCKED: $*" >&2; exit 77; }
TARGET="${1:-}"
[[ "$TARGET" =~ ^[0-9a-f]{40}$ ]] || fail 'full CI commit SHA required'
[[ "$EUID" != 0 ]] || fail 'run as deployment user'
for c in sudo docker curl python3 grep mktemp; do command -v "$c" >/dev/null || fail "missing $c"; done
ENV="$HOME/.config/mars/uat-fresh.env"
COMPOSE="$HOME/mars-uat-fresh-src/compose.uat.fresh.yml"
[[ -f "$ENV" && ! -L "$ENV" && -f "$COMPOSE" && ! -L "$COMPOSE" ]] || fail 'Fresh env/compose missing'
[[ "$(stat -c '%a:%U' "$ENV")" == "600:$(id -un)" ]] || fail 'Fresh env permissions invalid'
grep -qx 'MARS_FRESH_TAILSCALE_IP=100.127.235.30' "$ENV" || fail 'Fresh IP mismatch'
grep -qx 'MARS_FRESH_PORT=18082' "$ENV" || fail 'Fresh port mismatch'
OLD="$(sed -n 's/^MARS_UAT_SHA=//p' "$ENV")"
[[ "$OLD" =~ ^[0-9a-f]{40}$ ]] || fail 'invalid Fresh source image SHA'
[[ "$OLD" != "$TARGET" ]] || { echo "ALREADY_PINNED=$TARGET"; exit 0; }
PREFIX=ghcr.io/ufukunal/marsotomasyon
NEW_IMAGE="$PREFIX:uat-$TARGET"
OLD_IMAGE="$PREFIX:uat-$OLD"
PG=mars-uat-fresh-postgres-1
VK=mars-uat-fresh-valkey-1
WEB=mars-uat-fresh-web-1
sudo -v
sudo docker compose --env-file "$ENV" -f "$COMPOSE" config --quiet || fail 'Fresh compose invalid'
for spec in "postgres:$PG" "valkey:$VK" "web:$WEB"; do
  service="${spec%%:*}"
  name="${spec#*:}"
  state="$(sudo docker inspect "$name" --format '{{index .Config.Labels "com.docker.compose.project"}}:{{index .Config.Labels "com.docker.compose.service"}}:{{.State.Status}}:{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' 2>/dev/null)" || fail "container missing: $name"
  [[ "$state" == "mars-uat-fresh:$service:running:healthy" ]] || fail "container unhealthy/wrong project: $name ($state)"
done
[[ "$(sudo docker inspect "$WEB" --format '{{.Config.Image}}')" == "$OLD_IMAGE" ]] || fail 'Fresh current image does not match env'
sudo docker image inspect "$OLD_IMAGE" >/dev/null || fail 'old image missing; rollback impossible'
PGID="$(sudo docker inspect "$PG" --format '{{.Id}}')"
VKID="$(sudo docker inspect "$VK" --format '{{.Id}}')"
STORAGE="$(sudo docker inspect "$WEB" --format '{{range .Mounts}}{{if eq .Destination "/var/www/html/storage"}}{{.Name}}{{end}}{{end}}')"
[[ "$STORAGE" == mars-uat-fresh_fresh_storage ]] || fail 'unexpected Fresh storage'
echo '=== Fresh schema and effective role readiness ==='
SCHEMA="$(sudo docker exec "$PG" psql -w -U mars_uat_owner -d mars_uat_demo_2026 -XAtq -v ON_ERROR_STOP=1 -c "SELECT CASE WHEN EXISTS(SELECT 1 FROM information_schema.columns WHERE table_name='documents' AND column_name='additional_discount_amount') AND to_regclass('public.contact_transfers') IS NOT NULL THEN 'READY' ELSE 'MISSING' END")" || fail 'cannot inspect Fresh period DB'
[[ "$SCHEMA" == READY ]] || fail 'period schema incomplete'
PERMS="$(sudo docker exec "$PG" psql -w -U mars_uat_owner -d mars_uat_master -XAtq -v ON_ERROR_STOP=1 -c "SELECT count(*) FROM (SELECT p.id FROM permissions p JOIN role_has_permissions rp ON rp.permission_id=p.id WHERE p.guard_name='web' AND p.name IN ('contact_transfers.view','contact_transfers.create','contact_transfers.cancel','contact_transfers.override_balance') GROUP BY p.id HAVING count(rp.role_id)>0) a")" || fail 'cannot inspect Fresh role permissions'
[[ "$PERMS" == 4 ]] || fail 'not all four Fresh permissions assigned to roles'
if [[ "${MARS_FRESH_OFFLINE_IMAGE:-0}" == 1 ]]; then
  echo '=== Verify imported CI image (offline transport; PROD does NOT build) ==='
  sudo docker image inspect "$NEW_IMAGE" >/dev/null || fail 'offline CI image has not been loaded and verified'
else
  echo '=== Pull CI-built image (PROD does NOT build) ==='
  sudo docker pull "$NEW_IMAGE" || fail 'CI image unavailable, no deployment'
fi
[[ "$(sudo docker image inspect "$NEW_IMAGE" --format '{{index .Config.Labels "org.opencontainers.image.revision"}}')" == "$TARGET" ]] || fail 'CI image revision mismatch'
sudo docker run --rm --user 33:33 --entrypoint sh "$NEW_IMAGE" -ec '
  test -r /var/www/html/artisan
  test -s /var/www/html/public/build/manifest.json
  grep -Fq "contact_transfers.override_balance" /var/www/html/app/Support/Auth/CompanyRoleProvisioner.php
  php -l /var/www/html/app/Support/Auth/CompanyRoleProvisioner.php
' || fail 'pulled image basic validation failed'
# Replace exactly the one pinned value; never print or expose other secrets.
set_pin() {
  python3 - "$ENV" "$1" "$2" <<'PY'
from pathlib import Path
import os, sys, tempfile
path=Path(sys.argv[1]); old=sys.argv[2]; new=sys.argv[3]
content=path.read_text()
needle='MARS_UAT_SHA='+old+'\n'
if content.count(needle)!=1 or content.count('MARS_UAT_SHA=')!=1:
    raise SystemExit('unsafe or ambiguous Fresh image pin')
fd,tmp=tempfile.mkstemp(prefix='.uat-fresh-pin-',dir=path.parent)
try:
    os.fchmod(fd,0o600)
    with os.fdopen(fd,'w') as f:
        f.write(content.replace(needle,'MARS_UAT_SHA='+new+'\n'))
        f.flush(); os.fsync(f.fileno())
    os.replace(tmp,path)
except BaseException:
    try: os.unlink(tmp)
    except OSError: pass
    raise
PY
}
SWITCHED=0
SUCCESS=0
cleanup() {
  rc=$?
  trap - EXIT
  if (( rc != 0 && SWITCHED == 1 && SUCCESS == 0 )); then
    echo 'FRESH_WEB_RELEASE_FAILED=YES attempting rollback' >&2
    set_pin "$TARGET" "$OLD" || true
    sudo docker compose --env-file "$ENV" -f "$COMPOSE" up -d --no-deps --pull never --force-recreate web || true
    echo 'FRESH_WEB_ROLLBACK_ATTEMPTED=YES' >&2
  fi
  exit "$rc"
}
trap cleanup EXIT
echo '=== Switch ONLY the isolated Fresh web ==='
set_pin "$OLD" "$TARGET"
SWITCHED=1
sudo docker compose --env-file "$ENV" -f "$COMPOSE" up -d --no-deps --pull never --force-recreate web || fail 'Fresh web deployment failed'
READY=0
for attempt in $(seq 1 45); do
  state="$(sudo docker inspect "$WEB" --format '{{.State.Status}}:{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' 2>/dev/null || true)"
  http="$(curl --noproxy '*' --silent --output /dev/null --write-out '%{http_code}' --connect-timeout 2 --max-time 5 http://100.127.235.30:18082/giris || true)"
  if [[ "$state" == 'running:healthy' && "$http" == 200 ]]; then READY=1; break; fi
  sleep 2
done
[[ "$READY" == 1 ]] || fail 'Fresh web not healthy/login 200 after update'
[[ "$(sudo docker inspect "$WEB" --format '{{.Config.Image}}')" == "$NEW_IMAGE" ]] || fail 'Fresh web image mismatch'
[[ "$(sudo docker inspect "$PG" --format '{{.Id}}')" == "$PGID" ]] || fail 'Fresh Postgres container changed'
[[ "$(sudo docker inspect "$VK" --format '{{.Id}}')" == "$VKID" ]] || fail 'Fresh Valkey container changed'
[[ "$(sudo docker inspect "$WEB" --format '{{range .Mounts}}{{if eq .Destination "/var/www/html/storage"}}{{.Name}}{{end}}{{end}}')" == "$STORAGE" ]] || fail 'Fresh storage changed'
SUCCESS=1
echo "FRESH_23=PASS"
echo "RELEASE_SHA=$TARGET"
echo 'FRESH_LOGIN_HTTP=200'
echo 'FRESH_POSTGRES_UNCHANGED=YES'
echo 'FRESH_VALKEY_UNCHANGED=YES'
echo 'PROD_TOUCHED=NO'
