#!/usr/bin/env bash
# Fresh UAT ONLY. After 18 identity and 19 offline-restored backup:
# bash ops/uat-fresh-period-migrations.sh /absolute/path/to/fresh-backup
set -Eeuo pipefail
umask 077
stop(){ echo "BLOCKED: $*" >&2; exit 77; }
[[ "$EUID" != 0 ]] || stop 'run as non-root deployment user'
[[ "$#" == 1 ]] || stop 'verified Fresh backup directory argument required'
for c in sudo docker sha256sum grep stat; do command -v "$c" >/dev/null || stop "missing $c"; done
SCRIPT_DIR="$(cd -- "$(dirname -- "$0")" && pwd -P)"
ROOT="$(cd -- "$SCRIPT_DIR/.." && pwd -P)"
GATE="$SCRIPT_DIR/uat-fresh-identity-check.sh"
[[ -f "$GATE" && ! -L "$GATE" ]] || stop 'Fresh identity gate missing'
bash "$GATE" || stop 'Fresh identity mismatch'
BACKUP="$1"
[[ "$BACKUP" == "$HOME"/mars-fresh-backups/fresh-* ]] || stop 'backup outside dedicated Fresh path'
[[ -d "$BACKUP" && ! -L "$BACKUP" ]] || stop 'backup missing or symlink'
[[ "$(stat -c '%a:%U' "$BACKUP")" == "700:$(id -un)" ]] || stop 'backup ownership/mode mismatch'
[[ -f "$BACKUP/RESTORE_VERIFIED" && ! -L "$BACKUP/RESTORE_VERIFIED" ]] || stop 'offline restore proof missing'
grep -qx 'FRESH_BACKUP=RESTORE_VERIFIED' "$BACKUP/RESTORE_VERIFIED" || stop 'backup not verified'
grep -qx 'SOURCE_PROJECT=mars-uat-fresh' "$BACKUP/RESTORE_VERIFIED" || stop 'wrong project backup'
grep -qx 'SOURCE_MASTER=mars_uat_master' "$BACKUP/RESTORE_VERIFIED" || stop 'wrong master backup'
grep -qx 'SOURCE_PERIOD=mars_uat_demo_2026' "$BACKUP/RESTORE_VERIFIED" || stop 'wrong period backup'
(cd "$BACKUP" && sha256sum -c --status SHA256SUMS.sha256 && sha256sum -c --status SHA256SUMS) || stop 'backup integrity failure'
[[ -s "$BACKUP/mars_uat_master.dump" && -s "$BACKUP/mars_uat_demo_2026.dump" ]] || stop 'both archives needed'
ENV="$HOME/.config/mars/uat-fresh.env"
COMPOSE="$HOME/mars-uat-fresh-src/compose.uat.fresh.yml"
[[ -f "$ENV" && -f "$COMPOSE" ]] || stop 'Fresh compose/env unavailable'
PG=mars-uat-fresh-postgres-1
for MIGRATION in 0001_12_02_000010_add_document_additional_discount.php 0001_12_02_000020_create_contact_transfers.php; do
  LOCAL="$ROOT/database/migrations/period/$MIGRATION"
  [[ -f "$LOCAL" && ! -L "$LOCAL" ]] || stop "migration missing: $MIGRATION"
done
# Only approved files, mounted read-only into isolated one-off Fresh application.
# Explicit period connection prevents accidental writes to Fresh master or PROD.
for MIGRATION in 0001_12_02_000010_add_document_additional_discount.php 0001_12_02_000020_create_contact_transfers.php; do
  LOCAL="$ROOT/database/migrations/period/$MIGRATION"
  REMOTE="/var/www/html/database/migrations/period/$MIGRATION"
  echo "=== Fresh period migration: $MIGRATION ==="
  sudo docker compose --env-file "$ENV" -f "$COMPOSE" run --rm -T --no-deps \
    -v "$LOCAL:$REMOTE:ro" \
    -e DB_CONNECTION=period -e DB_PERIOD_DATABASE=mars_uat_demo_2026 \
    --entrypoint php web artisan migrate --database=period \
    --path="database/migrations/period/$MIGRATION" --force --no-interaction \
    || stop "migration failed: $MIGRATION"
done
RESULT="$(sudo docker exec "$PG" psql -U mars_uat_owner -d mars_uat_demo_2026 -XAtq -v ON_ERROR_STOP=1 -c "
SELECT CASE WHEN
 EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='documents' AND column_name='additional_discount_amount')
 AND to_regclass('public.contact_transfers') IS NOT NULL
 AND EXISTS (SELECT 1 FROM pg_constraint WHERE conname='documents_additional_discount_nonnegative')
 AND EXISTS (SELECT 1 FROM pg_constraint WHERE conname='contact_transfers_distinct_contacts')
 AND EXISTS (SELECT 1 FROM pg_constraint WHERE conname='contact_transfers_amount_positive')
THEN 'READY' ELSE 'MISSING' END;")" || stop 'Fresh schema verification failed'
[[ "$RESULT" == READY ]] || stop 'Fresh schema is incomplete'
echo 'FRESH_PERIOD_MIGRATIONS=PASS'
echo 'TARGET_DB=mars_uat_demo_2026'
echo 'MASTER_DB_MIGRATED=NO'
echo 'PROD_TOUCHED=NO'
