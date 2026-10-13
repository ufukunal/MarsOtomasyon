#!/usr/bin/env bash
# Only the isolated MarsProject Fresh WEB container is updated.
# Usage: bash ops/uat-fresh-web-update.sh FULL_COMMIT_SHA
# No migrations, no database/Valkey restarts, no volume operations.
set -Eeuo pipefail
umask 077
TARGET="${1:-}"
[[ "$TARGET" =~ ^[0-9a-f]{40}$ ]] || { echo 'ERROR: full commit SHA required' >&2; exit 2; }
(( EUID != 0 )) || { echo 'ERROR: run as ufuk, not root' >&2; exit 2; }
for c in sudo docker curl python3 grep sed mktemp; do command -v "$c" >/dev/null || { echo "MISSING: $c" >&2; exit 2; }; done
ROOT="$HOME/mars-uat-fresh-src"
ENV="$HOME/.config/mars/uat-fresh.env"
COMPOSE="$ROOT/compose.uat.fresh.yml"
PG=mars-uat-fresh-postgres-1
VK=mars-uat-fresh-valkey-1
WEB=mars-uat-fresh-web-1
REG=ghcr.io/ufukunal/marsotomasyon
fail() { echo "BLOCKED: $*" >&2; exit 3; }
sudo -v
[[ -f "$ENV" && ! -L "$ENV" && -f "$COMPOSE" && ! -L "$COMPOSE" ]] || fail 'Fresh env or compose missing'
[[ "$(stat -c '%a:%U' "$ENV")" == "600:$(id -un)" ]] || fail 'Fresh secret permissions invalid'
grep -qx 'MARS_FRESH_PORT=18082' "$ENV" || fail 'Unexpected port'
grep -qx 'MARS_FRESH_TAILSCALE_IP=100.127.235.30' "$ENV" || fail 'Unexpected IP'
OLD="$(sed -n 's/^MARS_UAT_SHA=//p' "$ENV")"
[[ "$OLD" =~ ^[0-9a-f]{40}$ ]] || fail 'Invalid previous image pin'
[[ "$OLD" != "$TARGET" ]] || { echo "ALREADY_PINNED=$TARGET"; exit 0; }

echo '=== FRESH PREFLIGHT ==='
for spec in "postgres:$PG" "valkey:$VK" "web:$WEB"; do
  service="${spec%%:*}"
  name="${spec#*:}"
  state="$(sudo docker inspect "$name" --format '{{index .Config.Labels "com.docker.compose.project"}}:{{index .Config.Labels "com.docker.compose.service"}}:{{.State.Status}}:{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' 2>/dev/null)" || fail "Missing container $name"
  [[ "$state" == "mars-uat-fresh:$service:running:healthy" ]] || fail "Not isolated/healthy: $name $state"
done
OLD_IMAGE="$REG:uat-$OLD"
NEW_IMAGE="$REG:uat-$TARGET"
[[ "$(sudo docker inspect "$WEB" --format '{{.Config.Image}}')" == "$OLD_IMAGE" ]] || fail 'Current Fresh web image differs from env pin'
sudo docker image inspect "$OLD_IMAGE" >/dev/null || fail 'Old web image not available for rollback'
VOLUME="$(sudo docker inspect "$WEB" --format '{{range .Mounts}}{{if eq .Destination "/var/www/html/storage"}}{{.Name}}{{end}}{{end}}')"
[[ -n "$VOLUME" ]] || fail 'Fresh web storage volume missing'
PGID="$(sudo docker inspect "$PG" -f '{{.Id}}')"
VKID="$(sudo docker inspect "$VK" -f '{{.Id}}')"
sudo docker compose --env-file "$ENV" -f "$COMPOSE" config --quiet || fail 'Compose invalid'

WORK="$(mktemp -d "$HOME/mars-fresh-release-XXXXXXXX")"
SWITCHED=0
SUCCESS=0
set_pin() {
  python3 - "$ENV" "$1" "$2" <<'PY'
from pathlib import Path
import os, tempfile, sys
p=Path(sys.argv[1]); old=sys.argv[2]; new=sys.argv[3]
content=p.read_text()
needle='MARS_UAT_SHA='+old+'\n'
if content.count(needle)!=1 or content.count('MARS_UAT_SHA=')!=1: raise SystemExit('Unexpected image pin')
fd,tmp=tempfile.mkstemp(prefix='.fresh-env-',dir=p.parent)
try:
    os.fchmod(fd,0o600)
    with os.fdopen(fd,'w') as out:
        out.write(content.replace(needle,'MARS_UAT_SHA='+new+'\n'))
        out.flush(); os.fsync(out.fileno())
    os.replace(tmp,p)
except BaseException:
    try: os.unlink(tmp)
    except OSError: pass
    raise
PY
}
cleanup() {
  rc=$?
  trap - EXIT
  if (( rc != 0 && SWITCHED == 1 && SUCCESS == 0 )); then
    echo "FAILED: rolling back Fresh web to $OLD" >&2
    set_pin "$TARGET" "$OLD" || true
    sudo docker compose --env-file "$ENV" -f "$COMPOSE" up -d --no-deps --pull never --force-recreate web || true
    echo 'ROLLBACK_ATTEMPTED=YES' >&2
  fi
  [[ ! -d "$WORK" ]] || rm -rf -- "$WORK"
  exit "$rc"
}
trap cleanup EXIT

echo '=== DOWNLOAD PINNED GITHUB SOURCE ==='
curl -fLsS --retry 3 --max-time 240 "https://codeload.github.com/ufukunal/MarsOtomasyon/zip/$TARGET" -o "$WORK/source.zip"
SOURCE="$(python3 - "$WORK" "$TARGET" <<'PY'
from pathlib import Path, PurePosixPath
from zipfile import ZipFile
import sys
root=Path(sys.argv[1]).resolve(); sha=sys.argv[2]; folder='MarsOtomasyon-'+sha
with ZipFile(root/'source.zip') as z:
    bad=z.testzip()
    if bad: raise SystemExit('Invalid source archive: '+bad)
    for item in z.infolist():
        parts=PurePosixPath(item.filename).parts
        if not parts or parts[0]!=folder or '..' in parts:
            raise SystemExit('Unexpected archive entry')
        if not (root/Path(*parts)).resolve().is_relative_to(root):
            raise SystemExit('Unsafe path')
        mode=(item.external_attr>>16)&0o170000
        if mode not in (0,0o100000,0o040000):
            raise SystemExit('Unsafe archive file type')
    z.extractall(root)
source=root/folder
for name in ['Dockerfile.uat','resources/views/layouts/app.blade.php','resources/views/livewire/pages/products/product-detail.blade.php','resources/views/livewire/components/data-table-pagination.blade.php','app/Http/Controllers/ProductTechnicalSheetController.php']:
    if not (source/name).is_file(): raise SystemExit('Missing '+name)
print(source)
PY
)"
# ZipFile.extractall respects umask 077: extracted files become root-only
# (0600, directories 0700) in Docker COPY layers. Laravel runs as www-data.
# Restore readability ONLY within the disposable GitHub source snapshot.
chmod -R a+rX "$SOURCE"
[[ -r "$SOURCE/artisan" && -r "$SOURCE/resources/css/v65-shell.css" ]] || fail 'Staged source is unreadable after permission repair'
VIEW="$SOURCE/resources/views/livewire/pages/products/product-detail.blade.php"
LAYOUT="$SOURCE/resources/views/layouts/app.blade.php"
grep -Fq "products.technical-sheet.show" "$VIEW" || fail 'Technical PDF link missing'
! grep -Fq 'Teklif Oluştur' "$VIEW" || fail 'Quote shortcut remains'
! grep -Fq 'Sipariş Oluştur' "$VIEW" || fail 'Order shortcut remains'
! grep -Fq 'v65-quick-invoice' "$VIEW" || fail 'Invoice shortcut remains'
! grep -Fq 'Alım İşlemleri' "$LAYOUT" || fail 'Purchase menu remains'
! grep -Fq 'Satış İşlemleri' "$LAYOUT" || fail 'Sales menu remains'

echo '=== BUILD NEW WEB IMAGE (DATA UNTOUCHED) ==='
sudo docker build -f "$SOURCE/Dockerfile.uat" --label "org.opencontainers.image.revision=$TARGET" -t "$NEW_IMAGE" "$SOURCE"
[[ "$(sudo docker image inspect "$NEW_IMAGE" -f '{{index .Config.Labels "org.opencontainers.image.revision"}}')" == "$TARGET" ]] || fail 'Build image SHA mismatch'
# Explicitly verify as www-data, and print the exact failed path/check.
sudo docker run --rm --user 33:33 --entrypoint sh "$NEW_IMAGE" -ec '
for f in /var/www/html/artisan /var/www/html/public/build/manifest.json /var/www/html/resources/views/livewire/pages/products/product-detail.blade.php /var/www/html/resources/css/v65-shell.css; do
  if [ ! -r "$f" ]; then echo "IMAGE_CHECK_FAIL unreadable=$f" >&2; exit 1; fi
done
test -s /var/www/html/public/build/manifest.json || { echo "IMAGE_CHECK_FAIL Vite manifest empty" >&2; exit 1; }
grep -Fq "products.technical-sheet.show" /var/www/html/resources/views/livewire/pages/products/product-detail.blade.php || { echo "IMAGE_CHECK_FAIL technical PDF link missing" >&2; exit 1; }
grep -Fq "mars-pagination" /var/www/html/resources/css/v65-shell.css || { echo "IMAGE_CHECK_FAIL compact pagination CSS missing" >&2; exit 1; }
php -l /var/www/html/app/Http/Controllers/ProductTechnicalSheetController.php
# Validate the newly added contact detail before any web-container switch.
php -l /var/www/html/app/Livewire/Pages/Contacts/ContactDetail.php
php -l /var/www/html/app/Livewire/Pages/Contacts/ContactList.php
php -l /var/www/html/app/Livewire/Sales/BaseSalesDocumentEditor.php
# Compile and PHP-lint every Blade template inside the disposable image BEFORE
# replacing the running Fresh web container. php -l on Livewire classes alone
# cannot catch errors in the compiled view code.
php /var/www/html/artisan view:cache >/dev/null || { echo "IMAGE_CHECK_FAIL Blade compilation failed" >&2; exit 1; }
compiled_count=0
for compiled in /var/www/html/storage/framework/views/*.php; do
  [ -f "$compiled" ] || continue
  compiled_count=$((compiled_count + 1))
  php -l "$compiled" >/dev/null || { echo "IMAGE_CHECK_FAIL compiled Blade syntax: $compiled" >&2; exit 1; }
done
[ "$compiled_count" -gt 0 ] || { echo "IMAGE_CHECK_FAIL no compiled Blade templates found" >&2; exit 1; }
echo "IMAGE_BLADE_LINT_OK=$compiled_count"
test -s /var/www/html/resources/views/livewire/pages/contacts/contact-detail.blade.php || { echo "IMAGE_CHECK_FAIL contact detail Blade missing" >&2; exit 1; }
grep -Fq "exportContactDocumentsCsv" /var/www/html/app/Livewire/Pages/Contacts/ContactDetail.php || { echo "IMAGE_CHECK_FAIL contact document export missing" >&2; exit 1; }
grep -Fq "v65-contact-tabs" /var/www/html/resources/views/livewire/pages/contacts/contact-detail.blade.php || { echo "IMAGE_CHECK_FAIL contact detail tabs missing" >&2; exit 1; }
grep -Fq "v65-list-footer" /var/www/html/resources/views/components/v65/list-footer.blade.php || { echo "IMAGE_CHECK_FAIL shared list footer missing" >&2; exit 1; }
echo "IMAGE_CHECK_OK=YES (www-data readable and UI code present)"
' || fail 'Built image validation failed; Fresh is unchanged'

echo '=== RECREATE ONLY FRESH WEB WITH ROLLBACK ==='
set_pin "$OLD" "$TARGET"
SWITCHED=1
sudo docker compose --env-file "$ENV" -f "$COMPOSE" up -d --no-deps --pull never --force-recreate web
ready=0
for attempt in $(seq 1 40); do
  health="$(sudo docker inspect "$WEB" --format '{{.State.Status}}:{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' 2>/dev/null || true)"
  login="$(curl --noproxy '*' -sS --connect-timeout 2 --max-time 5 -o /dev/null -w '%{http_code}' 'http://100.127.235.30:18082/giris' 2>/dev/null || true)"
  if [[ "$health" == 'running:healthy' && "$login" == 200 ]]; then ready=1; break; fi
  sleep 2
done
[[ "$ready" == 1 ]] || fail 'New Fresh web did not become healthy with HTTP 200 login'
[[ "$(sudo docker inspect "$WEB" -f '{{.Config.Image}}')" == "$NEW_IMAGE" ]] || fail 'Unexpected web image'
[[ "$(sudo docker inspect "$WEB" -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/storage"}}{{.Name}}{{end}}{{end}}')" == "$VOLUME" ]] || fail 'Fresh storage volume changed'
[[ "$(sudo docker inspect "$PG" -f '{{.Id}}')" == "$PGID" ]] || fail 'Fresh PG container changed'
[[ "$(sudo docker inspect "$VK" -f '{{.Id}}')" == "$VKID" ]] || fail 'Fresh Valkey container changed'
sudo docker exec "$WEB" php artisan view:clear
SUCCESS=1
echo "RELEASE_OK=$TARGET"
echo 'FRESH_LOGIN_HTTP=200'
echo 'POSTGRES_UNCHANGED=YES VALKEY_UNCHANGED=YES STORAGE_UNCHANGED=YES'
