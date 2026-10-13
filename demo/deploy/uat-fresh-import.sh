#!/usr/bin/env bash
# MarsProject Rich UAT Demo — only isolated mars-uat-fresh (18082).
# Usage: bash deploy/uat-fresh-import.sh --check | --apply
# No git/gh/apt, no DB reset, no migrations, no volume deletion, no old UAT modifications.
set -Eeuo pipefail
umask 077

MODE="${1:---check}"
[[ "$MODE" == '--check' || "$MODE" == '--apply' ]] || { echo 'Usage: --check | --apply' >&2; exit 2; }
[[ "${EUID}" -ne 0 ]] || { echo 'BLOCKED: run as ufuk, not root' >&2; exit 2; }

ROOT="$HOME/mars-uat-fresh-src"
ENV_FILE="$HOME/.config/mars/uat-fresh.env"
COMPOSE="$ROOT/compose.uat.fresh.yml"
BASE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
SEEDER="$BASE/laravel/database/seeders/UatRichDemoSeeder.php"
PREFLIGHT="$BASE/deploy/preflight.php"
PG='mars-uat-fresh-postgres-1'
VK='mars-uat-fresh-valkey-1'
WEB='mars-uat-fresh-web-1'
BACKUP_ROOT="$HOME/mars-uat-fresh-backups"

die() { printf 'BLOCKED: %s\n' "$*" >&2; exit 3; }

printf '=== UAT FRESH DATASET %s ===\n' "$MODE"
command -v python3 >/dev/null || die 'python3 missing'
command -v sudo >/dev/null || die 'sudo missing'
sudo -v || die 'sudo authorization failed'
[[ -d "$ROOT" && -f "$COMPOSE" && -f "$ENV_FILE" ]] || die 'fresh source/compose/env missing'
[[ ! -L "$COMPOSE" && ! -L "$ENV_FILE" ]] || die 'unexpected compose/env symlink'
[[ -f "$BASE/manifest.json" && -f "$SEEDER" && -f "$PREFLIGHT" ]] || die 'incomplete extracted package'
[[ "$(stat -c '%a' "$ENV_FILE")" == 600 ]] || die 'UAT env must have permissions 600'
[[ "$(stat -c '%U' "$ENV_FILE")" == "$(id -un)" ]] || die 'UAT env has another owner'
grep -qx 'MARS_FRESH_PORT=18082' "$ENV_FILE" || die 'not UAT port 18082'
grep -qx 'MARS_FRESH_TAILSCALE_IP=100.127.235.30' "$ENV_FILE" || die 'unexpected UAT IP'

python3 - "$BASE" <<'PY'
import csv, json, pathlib, sys
root = pathlib.Path(sys.argv[1])
m = json.loads((root/'manifest.json').read_text())
if m.get('version') != '2026.10.13.1' or m.get('target_period') != 'mars_uat_demo_2026':
    sys.exit('BLOCKED: wrong UAT manifest or target period')
required = {'products':250, 'contacts':100, 'locations':10, 'documents':1210,
            'document_lines':2740, 'stock_balances':750, 'import_files':4,
            'containers':4, 'packages':120, 'production_recipes':60, 'production_orders':40}
for name, n in required.items():
    with (root/'csv'/f'{name}.csv').open(newline='', encoding='utf-8-sig') as fp:
        rows = list(csv.DictReader(fp))
    if len(rows) != n or m['counts'].get(name) != n:
        sys.exit(f'BLOCKED: {name} count mismatch: {len(rows)} vs {n}')
for row in csv.DictReader((root/'csv'/'products.csv').open(newline='', encoding='utf-8-sig')):
    for k in ('image_main','image_detail','technical_sheet'):
        sub = pathlib.Path(row[k]); full = root/sub
        if sub.is_absolute() or '..' in sub.parts or not full.is_file() or full.stat().st_size < 1000:
            sys.exit(f'BLOCKED: missing/unsafe asset: {row["sku"]}/{k}')
print('PASS: CSV counts, asset paths, manifest target')
PY

actual_pg="$(sudo docker inspect "$PG" --format '{{index .Config.Labels "com.docker.compose.project"}}:{{index .Config.Labels "com.docker.compose.service"}}:{{.State.Status}}' 2>/dev/null)" || die 'fresh Postgres missing'
actual_vk="$(sudo docker inspect "$VK" --format '{{index .Config.Labels "com.docker.compose.project"}}:{{index .Config.Labels "com.docker.compose.service"}}:{{.State.Status}}' 2>/dev/null)" || die 'fresh Valkey missing'
actual_web="$(sudo docker inspect "$WEB" --format '{{index .Config.Labels "com.docker.compose.project"}}:{{index .Config.Labels "com.docker.compose.service"}}:{{.State.Status}}' 2>/dev/null)" || die 'fresh web missing'
[[ "$actual_pg" == 'mars-uat-fresh:postgres:running' ]] || die "wrong/stop PostgreSQL: $actual_pg"
[[ "$actual_vk" == 'mars-uat-fresh:valkey:running' ]] || die "wrong/stop Valkey: $actual_vk"
[[ "$actual_web" == 'mars-uat-fresh:web:running' ]] || die "wrong/stop web: $actual_web"
for c in "$PG" "$VK" "$WEB"; do
    h="$(sudo docker inspect "$c" --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}')"
    [[ "$h" == healthy ]] || die "not healthy: $c $h"
done

# After setup creates the first user, CompanyWizard::mount() intentionally
# returns HTTP 403 for unauthenticated /kurulum requests. A previously valid
# installation must not be rejected merely because setup is protected.
echo '=== FRESH HTTP CHECK ==='
login_http="$(curl --noproxy '*' -sS --connect-timeout 3 --max-time 10 -o /dev/null -w '%{http_code}' 'http://100.127.235.30:18082/giris' 2>/dev/null)" || die '/giris HTTP not reachable'
[[ "$login_http" == 200 ]] || die "/giris HTTP=$login_http"
setup_http="$(curl --noproxy '*' -sS --connect-timeout 3 --max-time 10 -o /dev/null -w '%{http_code}' 'http://100.127.235.30:18082/kurulum' 2>/dev/null)" || die '/kurulum HTTP not reachable'
[[ "$setup_http" == 200 || "$setup_http" == 403 ]] || die "/kurulum HTTP=$setup_http (expected 200 before installation, 403 after setup)"
printf 'PASS: /giris HTTP=%s; /kurulum HTTP=%s (setup can be protected after first user)\n' "$login_http" "$setup_http"
# Real readiness is also checked below using Laravel Master/period schema,
# exact isolated database names and active company period.

DC=(sudo docker compose --env-file "$ENV_FILE" -f "$COMPOSE")
"${DC[@]}" config --quiet || die 'Compose validation failed'

# Compose run creates a disposable one-off web container. Its root remains read-only,
# while demo source/fixtures are bind-mounted read-only; DB writes target fresh only.
COMMON=(-T --no-deps -v "$BASE:/var/www/html/storage/app/uat-demo-rich:ro" -v "$SEEDER:/var/www/html/database/seeders/UatRichDemoSeeder.php:ro")

echo '=== PHP SYNTAX CHECK (DISPOSABLE WEB CONTAINER) ==='
"${DC[@]}" run --rm "${COMMON[@]}" web sh -c  'php -l /var/www/html/database/seeders/UatRichDemoSeeder.php && php -l /var/www/html/storage/app/uat-demo-rich/deploy/preflight.php && php -l /var/www/html/storage/app/uat-demo-rich/deploy/postflight.php' || die 'PHP syntax check failed'

echo '=== READ-ONLY LARAVEL/DB PREFLIGHT ===' 
"${DC[@]}" run --rm "${COMMON[@]}" web php /var/www/html/storage/app/uat-demo-rich/deploy/preflight.php || die 'Laravel/DB preflight failed'

if [[ "$MODE" == '--check' ]]; then
    echo 'PASS: READ-ONLY CHECK DONE. No data imported.'
    echo 'NEXT: bash deploy/uat-fresh-import.sh --apply'
    exit 0
fi

mkdir -p "$BACKUP_ROOT"
chmod 700 "$BACKUP_ROOT"
backup="$BACKUP_ROOT/mars_uat_demo_2026_before_rich_demo_$(date -u +%Y%m%dT%H%M%SZ).dump"
echo '=== BACKUP ONLY FRESH PERIOD DATABASE ==='
sudo docker exec "$PG" pg_dump -U mars_uat_owner -Fc -d mars_uat_demo_2026 > "$backup" || die 'fresh period DB backup failed'
[[ -s "$backup" ]] || die 'DB backup is empty'
sudo docker exec -i "$PG" pg_restore --list < "$backup" >/dev/null || die 'DB backup archive failed verification'
echo "BACKUP_OK=$backup"

echo '=== APPLY DATASET: CORE CARDS + DRAFT DOCUMENTS ==='
"${DC[@]}" run --rm "${COMMON[@]}" -e MARS_UAT_RICH_DEMO_SEED=YES web php artisan db:seed '--class=Database\Seeders\UatRichDemoSeeder' --force

echo '=== VERIFY DATABASE COUNTS / HTTP ==='
"${DC[@]}" run --rm "${COMMON[@]}" web php /var/www/html/storage/app/uat-demo-rich/deploy/postflight.php || die 'postflight dataset counts failed'
login_http="$(curl --noproxy '*' -sS --connect-timeout 3 --max-time 10 -o /dev/null -w '%{http_code}' 'http://100.127.235.30:18082/giris' 2>/dev/null)" || login_http=000
printf 'GIRIS_HTTP=%s\n' "$login_http"
[[ "$login_http" == 200 ]] || die "postflight /giris HTTP=$login_http"
setup_http="$(curl --noproxy '*' -sS --connect-timeout 3 --max-time 10 -o /dev/null -w '%{http_code}' 'http://100.127.235.30:18082/kurulum' 2>/dev/null)" || setup_http=000
printf 'KURULUM_HTTP=%s\n' "$setup_http"
printf 'IMPORT_EXIT=0\n'
echo 'NOTE: Settled/posted accounting, cost allocations, channels and other scenario-only CSV are NOT imported as actual completed transactions.'
