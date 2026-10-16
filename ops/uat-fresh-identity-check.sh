#!/usr/bin/env bash
# Read-only Fresh UAT identity gate. Never logs credentials or writes to a database.
# Run as the ordinary deployment account on the Fresh UAT Docker host.
set -Eeuo pipefail
umask 077

fail() { printf 'BLOCKED: %s\n' "$*" >&2; exit 77; }
for bin in sudo docker curl grep stat id; do
  command -v "$bin" >/dev/null 2>&1 || fail "missing executable: $bin"
done
[ "$(id -u)" -ne 0 ] || fail 'run as the deployment account, not root'

ROOT="$HOME/mars-uat-fresh-src"
ENV_FILE="$HOME/.config/mars/uat-fresh.env"
COMPOSE="$ROOT/compose.uat.fresh.yml"
[ -f "$ENV_FILE" ] && [ ! -L "$ENV_FILE" ] || fail 'Fresh UAT env is missing or symlinked'
[ -f "$COMPOSE" ] && [ ! -L "$COMPOSE" ] || fail 'Fresh UAT compose is missing or symlinked'
[ "$(stat -c '%a:%U' "$ENV_FILE")" = "600:$(id -un)" ] || fail 'Fresh UAT env permissions/owner mismatch'
grep -qx 'MARS_FRESH_TAILSCALE_IP=100.127.235.30' "$ENV_FILE" || fail 'unexpected Fresh IP'
grep -qx 'MARS_FRESH_PORT=18082' "$ENV_FILE" || fail 'unexpected Fresh port'
grep -q '^MARS_UAT_SHA=[0-9a-f]\{40\}$' "$ENV_FILE" || fail 'Fresh image must be pinned by full SHA'
sudo -v
sudo docker compose --env-file "$ENV_FILE" -f "$COMPOSE" config --quiet || fail 'invalid Fresh compose file'

for item in postgres valkey web; do
  name="mars-uat-fresh-$item-1"
  label="$(sudo docker inspect "$name" --format '{{index .Config.Labels "com.docker.compose.project"}}:{{index .Config.Labels "com.docker.compose.service"}}:{{.State.Status}}' 2>/dev/null)" || fail "container missing: $name"
  [ "$label" = "mars-uat-fresh:$item:running" ] || fail "unexpected container identity/status: $name"
done

PG=mars-uat-fresh-postgres-1
VK=mars-uat-fresh-valkey-1
WEB=mars-uat-fresh-web-1

[ "$(sudo docker inspect "$PG" --format '{{range .Mounts}}{{if eq .Destination "/var/lib/postgresql/data"}}{{.Name}}{{end}}{{end}}')" = 'mars-uat-fresh_fresh_pg' ] || fail 'Fresh Postgres volume identity mismatch'
[ "$(sudo docker inspect "$VK" --format '{{range .Mounts}}{{if eq .Destination "/data"}}{{.Name}}{{end}}{{end}}')" = 'mars-uat-fresh_fresh_valkey' ] || fail 'Fresh Valkey volume identity mismatch'
[ "$(sudo docker inspect "$WEB" --format '{{range .Mounts}}{{if eq .Destination "/var/www/html/storage"}}{{.Name}}{{end}}{{end}}')" = 'mars-uat-fresh_fresh_storage' ] || fail 'Fresh web storage volume identity mismatch'

# Confirm the two expected DBs are inside the isolated Fresh PostgreSQL container.
DB_NAMES="$(sudo docker exec "$PG" psql -w -XAtq -v ON_ERROR_STOP=1 -U mars_uat_owner -d postgres -c "SELECT string_agg(datname, ',' ORDER BY datname) FROM pg_database WHERE datname LIKE 'mars_%';" 2>/dev/null)" || fail 'unable to query isolated Fresh database names'
[ "$DB_NAMES" = 'mars_uat_demo_2026,mars_uat_master' ] || fail 'unexpected Fresh database set'
PERIOD_NAME="$(sudo docker exec "$PG" psql -w -XAtq -v ON_ERROR_STOP=1 -U mars_uat_owner -d mars_uat_demo_2026 -c 'SELECT current_database();' 2>/dev/null)" || fail 'cannot connect to Fresh period database'
[ "$PERIOD_NAME" = 'mars_uat_demo_2026' ] || fail 'Fresh period database mismatch'

MASTER_MAPPING="$(sudo docker exec "$PG" psql -w -XAtq -v ON_ERROR_STOP=1 -U mars_uat_owner -d mars_uat_master -c "SELECT count(*) FROM periods WHERE database_name='mars_uat_demo_2026' AND year=2026 AND status='active';" 2>/dev/null)" || fail 'cannot validate Fresh master period mapping'
[ "$MASTER_MAPPING" = '1' ] || fail 'Fresh period is missing, inactive or mapped ambiguously'
PIN_SHA="$(sed -n 's/^MARS_UAT_SHA=//p' "$ENV_FILE")"
IMAGE="$(sudo docker inspect "$WEB" --format '{{.Config.Image}}' 2>/dev/null)" || fail 'cannot read Fresh web image'
[ "$IMAGE" = "ghcr.io/ufukunal/marsotomasyon:uat-$PIN_SHA" ] || fail 'Fresh web image does not match its pinned commit'

# Explicit runtime identity; do not dump container environment (contains secrets).
APP_ID="$(sudo docker exec "$WEB" php -r '
$e = getenv("APP_ENV"); $m = getenv("DB_MASTER_DATABASE");
$p = getenv("MARS_UAT_PREPROVISIONED_PERIOD_DB");
$r = getenv("REDIS_PREFIX"); $n = getenv("APP_NAME");
echo implode("|", [$e,$m,$p,$r,$n]);
' 2>/dev/null)" || fail 'unable to read Fresh web runtime identity'
[ "$APP_ID" = 'uat|mars_uat_master|mars_uat_demo_2026|mars:fresh:|MarsProjectFreshUAT' ] || fail 'Fresh web runtime identity mismatch'

HTTP="$(curl --noproxy '*' -sS --connect-timeout 3 --max-time 10 -o /dev/null -w '%{http_code}' 'http://100.127.235.30:18082/up' 2>/dev/null)" || fail 'Fresh health endpoint unavailable'
[ "$HTTP" = '200' ] || fail "Fresh health endpoint returned $HTTP"

printf 'FRESH_IDENTITY=PASS\n'
printf 'PROJECT=mars-uat-fresh\nMASTER_DB=mars_uat_master\nPERIOD_DB=mars_uat_demo_2026\n'
printf 'APP_ENV=uat\nREDIS_NAMESPACE=mars:fresh:\nFRESH_HEALTH_HTTP=200\n'
printf 'READ_ONLY=YES\n'
