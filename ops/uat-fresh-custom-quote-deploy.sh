#!/usr/bin/env bash
# Fresh UAT ONLY. Custom quote migration + isolated web update with rollback.
# Usage: bash uat-fresh-custom-quote-deploy.sh FULL_COMMIT_SHA
set -Eeuo pipefail
umask 077
[[ "$#" -eq 1 ]] || { echo 'BLOCKED: full commit SHA required' >&2; exit 77; }
TARGET="$1"
[[ "$TARGET" =~ ^[a-f0-9]{40}$ ]] || { echo 'BLOCKED: invalid commit SHA' >&2; exit 77; }
[[ "$(id -un)" == 'ufuk' && "$EUID" -ne 0 ]] || { echo 'BLOCKED: expected nonroot ufuk' >&2; exit 77; }
for cmd in sudo docker curl grep stat mktemp sha256sum sed; do
  command -v "$cmd" >/dev/null || { echo "BLOCKED: missing $cmd" >&2; exit 77; }
done
HERE="$(cd "$(dirname "$0")" && pwd -P)"
for source in uat-fresh-identity-check.sh uat-fresh-backup-verify.sh uat-fresh-web-update.sh; do
  [[ -f "$HERE/$source" && ! -L "$HERE/$source" ]] || { echo "BLOCKED: missing $source" >&2; exit 77; }
  bash -n "$HERE/$source"
done
fail() { echo "BLOCKED: $*" >&2; exit 77; }

echo '=== 1/5 FRESH IDENTITY ==='
bash "$HERE/uat-fresh-identity-check.sh" || fail 'fresh identity failed'

echo '=== 2/5 BACKUP + OFFLINE RESTORE ==='
BACKUP_LOG="$(mktemp "$HERE/backup-verification.XXXXXX")"
chmod 0600 "$BACKUP_LOG"
trap 'rm -f -- "$BACKUP_LOG"' EXIT
bash "$HERE/uat-fresh-backup-verify.sh" | tee "$BACKUP_LOG"
grep -qx 'OFFLINE_RESTORE=PASS' "$BACKUP_LOG" || fail 'offline restore not verified'
BACKUP="$(sed -n 's/^BACKUP_PATH=//p' "$BACKUP_LOG")"
[[ "$BACKUP" == "$HOME"/mars-fresh-backups/fresh-* ]] || fail 'unsafe backup location'
[[ -d "$BACKUP" && ! -L "$BACKUP" && -f "$BACKUP/RESTORE_VERIFIED" ]] || fail 'restore proof missing'
[[ "$(stat -c '%a:%U' "$BACKUP")" == "700:$(id -un)" ]] || fail 'backup ownership invalid'
grep -qx 'FRESH_BACKUP=RESTORE_VERIFIED' "$BACKUP/RESTORE_VERIFIED" || fail 'restore proof invalid'
(cd "$BACKUP" && sha256sum -c --status SHA256SUMS && sha256sum -c --status SHA256SUMS.sha256) || fail 'backup hashes invalid'
echo 'FRESH_BACKUP=RESTORE_VERIFIED'

echo '=== 3/5 CUSTOM QUOTE PERIOD MIGRATION ==='
PG=mars-uat-fresh-postgres-1
ENV="$HOME/.config/mars/uat-fresh.env"
COMPOSE="$HOME/mars-uat-fresh-src/compose.uat.fresh.yml"
[[ -f "$ENV" && -f "$COMPOSE" ]] || fail 'isolated Fresh compose missing'
sudo docker exec "$PG" psql -U mars_uat_owner -d mars_uat_demo_2026 \
  -XAtq -v ON_ERROR_STOP=1 -c 'SELECT current_database()' | grep -Fxq 'mars_uat_demo_2026' \
  || fail 'period database mismatch'
for NAME in \
  2026_10_20_000001_add_custom_quote_document_lines.php \
  2026_10_20_000002_create_quote_custom_image_edits.php
do
  MIGRATION="$HERE/$NAME"
  [[ -f "$MIGRATION" && ! -L "$MIGRATION" ]] || fail "pinned migration missing: $NAME"
  sudo docker compose --env-file "$ENV" -f "$COMPOSE" run --rm -T --no-deps \
    -v "$MIGRATION:/var/www/html/database/migrations/period/$NAME:ro" \
    -e DB_CONNECTION=period -e DB_PERIOD_DATABASE=mars_uat_demo_2026 \
    --entrypoint php web artisan migrate --database=period \
    --path="database/migrations/period/$NAME" --force --no-interaction \
    || fail "custom quote migration failed: $NAME"
done

RESULT="$(sudo docker exec "$PG" psql -w -U mars_uat_owner -d mars_uat_demo_2026 \
  -XAtq -v ON_ERROR_STOP=1 -c "
SELECT CASE WHEN
  to_regclass('public.quote_custom_code_seq') IS NOT NULL
  AND has_sequence_privilege('mars_uat_app', 'public.quote_custom_code_seq', 'USAGE')
  AND EXISTS (SELECT 1 FROM pg_constraint
    WHERE conname='document_lines_kind_valid' AND pg_get_constraintdef(oid) LIKE '%custom%')
  AND EXISTS (SELECT 1 FROM pg_constraint
    WHERE conname='document_lines_stock_shape_valid' AND pg_get_constraintdef(oid) LIKE '%custom%')
  AND EXISTS (SELECT 1 FROM migrations
    WHERE migration='2026_10_20_000001_add_custom_quote_document_lines')
  AND to_regclass('public.quote_custom_image_edits') IS NOT NULL
  AND EXISTS (SELECT 1 FROM migrations
    WHERE migration='2026_10_20_000002_create_quote_custom_image_edits')
THEN 'PASS' ELSE 'FAIL' END;
")" || fail 'cannot verify custom quote schema'
[[ "$RESULT" == PASS ]] || fail 'custom quote schema not ready'
echo 'CUSTOM_QUOTE_SCHEMA=PASS'

echo '=== 4/5 BUILD AND ACTIVATE VERIFIED FRESH WEB ==='
bash "$HERE/uat-fresh-web-update.sh" "$TARGET" || fail 'Fresh web update failed (check rollback output)'
IMAGE="ghcr.io/ufukunal/marsotomasyon:uat-$TARGET"
[[ "$(sudo docker inspect mars-uat-fresh-web-1 -f '{{.Config.Image}}')" == "$IMAGE" ]] \
    || fail 'running image is not target'
sudo docker exec mars-uat-fresh-web-1 sh -ec '
  ROOT=/var/www/html
  grep -Fq "addCustomLine" "$ROOT/resources/views/components/v65/document-prototype-toolbar.blade.php"
  grep -Fq "x-on:paste" "$ROOT/resources/views/livewire/sales/document-editor.blade.php"
  grep -Fq "forCustomDocument" "$ROOT/app/Support/Printing/BusinessDocumentImageResolver.php"
  grep -Fq "Çıktı Tasarımını Seçin" "$ROOT/resources/views/components/v65/document-print-options.blade.php"
  grep -Fq "PDF İndir" "$ROOT/resources/views/components/v65/document-print-options.blade.php"
  test -s "$ROOT/resources/js/quote-image-editor.js"
  grep -Fq "v65-quote-image-editor" "$ROOT/resources/views/components/v65/quote-image-editor.blade.php"
' || fail 'custom quote UI/PDF source verification failed'

echo '=== 5/5 FRESH HEALTH ==='
HTTP="$(curl --noproxy '*' -sS --max-time 10 -o /dev/null -w '%{http_code}' 'http://100.127.235.30:18082/giris')"
[[ "$HTTP" == 200 ]] || fail 'Fresh login HTTP is not 200'
echo "RELEASE_OK=$TARGET"
echo "CUSTOM_QUOTE_SCHEMA=PASS"
echo "CUSTOM_QUOTE_UI=PASS"
echo "FRESH_LOGIN_HTTP=$HTTP"
echo 'PROD_TOUCHED=NO'
