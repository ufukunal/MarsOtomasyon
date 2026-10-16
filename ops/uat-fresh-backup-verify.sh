#!/usr/bin/env bash
# Backup isolated Fresh UAT master+period and test real restoration offline.
set -Eeuo pipefail
umask 077
die(){ printf 'BLOCKED: %s\n' "$*" >&2; exit 77; }
[[ "$EUID" != 0 ]] || die 'run as normal UAT user'
for c in sudo docker sha256sum stat mktemp df awk date; do command -v "$c" >/dev/null || die "missing: $c"; done
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
GATE="$SCRIPT_DIR/uat-fresh-identity-check.sh"
[[ -f "$GATE" && ! -L "$GATE" ]] || die 'identity gate absent: sync checkout'
bash "$GATE" || die 'Fresh identity gate failed'

PG=mars-uat-fresh-postgres-1
BASE="$HOME/mars-fresh-backups"
[[ ! -L "$BASE" ]] || die 'symlinked backup directory'
mkdir -p "$BASE"; chmod 700 "$BASE"
[[ "$(stat -c '%a:%U' "$BASE")" == "700:$(id -un)" ]] || die 'backup directory ownership'
FREE="$(df -Pk "$BASE" | awk 'NR==2{print $4}')"
[[ "$FREE" =~ ^[0-9]+$ && "$FREE" -ge 2097152 ]] || die '2 GB free space required'
TAG="$(date -u +%Y%m%dT%H%M%SZ)-$$"
BACKUP="$BASE/fresh-$TAG"
mkdir -m 700 "$BACKUP"
CTR="mars-fresh-restore-$TAG"
VOL="mars_fresh_restore_$TAG"
VOL_CREATED=0
CTR_CREATED=0
cleanup(){
  rc=$?
  trap - EXIT
  if (( CTR_CREATED )); then sudo docker rm -f "$CTR" >/dev/null 2>&1 || true; fi
  if (( VOL_CREATED )); then sudo docker volume rm "$VOL" >/dev/null 2>&1 || true; fi
  if (( rc != 0 )); then echo "BACKUP_VERIFICATION=FAILED; retained: $BACKUP" >&2; fi
  exit "$rc"
}
trap cleanup EXIT

echo '=== Fresh PostgreSQL backup (read-only) ==='
for DB in mars_uat_master mars_uat_demo_2026; do
  sudo docker exec "$PG" pg_dump -U mars_uat_owner -d "$DB" -Fc -w --no-owner --no-acl > "$BACKUP/$DB.dump" || die "dump failed: $DB"
  [[ -s "$BACKUP/$DB.dump" ]] || die "empty archive: $DB"
  sudo docker exec -i "$PG" pg_restore --list < "$BACKUP/$DB.dump" >/dev/null || die "invalid archive: $DB"
done
(cd "$BACKUP" && sha256sum mars_uat_master.dump mars_uat_demo_2026.dump > SHA256SUMS && sha256sum -c --status SHA256SUMS) || die 'backup checksum failed'

echo '=== Offline PostgreSQL restore validation ==='
sudo docker volume create --label mars.fresh.restore=temporary "$VOL" >/dev/null || die 'restore volume create failed'
VOL_CREATED=1
sudo docker run -d --name "$CTR" --network none --memory 768m --cpus 0.75 --pids-limit 128 \
  -e POSTGRES_HOST_AUTH_METHOD=trust \
  --mount "type=volume,source=$VOL,target=/var/lib/postgresql/data" \
  postgres:16 -c listen_addresses='' >/dev/null || die 'offline restore container failed'
CTR_CREATED=1
READY=0
for attempt in $(seq 1 45); do
  if sudo docker exec "$CTR" pg_isready -U postgres -d postgres >/dev/null 2>&1; then READY=1; break; fi
  sleep 2
done
[[ "$READY" == 1 ]] || die 'offline PostgreSQL not ready'

for DB in mars_uat_master mars_uat_demo_2026; do
  case "$DB" in
    mars_uat_master) TEST_DB=restore_master; CHECK_TABLE=users ;;
    mars_uat_demo_2026) TEST_DB=restore_period; CHECK_TABLE=documents ;;
  esac
  sudo docker exec "$CTR" createdb -U postgres "$TEST_DB" || die "scratch DB failed: $TEST_DB"
  sudo docker exec -i "$CTR" pg_restore -U postgres -d "$TEST_DB" --no-owner --no-acl --exit-on-error \
    < "$BACKUP/$DB.dump" || die "full restore failed: $DB"
  COUNT="$(sudo docker exec "$CTR" psql -U postgres -d "$TEST_DB" -XAtq -v ON_ERROR_STOP=1 \
    -c "SELECT count(*) FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE'")" || die "scratch table scan failed: $DB"
  [[ "$COUNT" =~ ^[0-9]+$ && "$COUNT" -gt 0 ]] || die "no restored tables: $DB"
  SRC_COUNT="$(sudo docker exec "$PG" psql -U mars_uat_owner -d "$DB" -XAtq -v ON_ERROR_STOP=1 \
    -c "SELECT count(*) FROM public.$CHECK_TABLE")" || die "source count failed: $DB"
  DST_COUNT="$(sudo docker exec "$CTR" psql -U postgres -d "$TEST_DB" -XAtq -v ON_ERROR_STOP=1 \
    -c "SELECT count(*) FROM public.$CHECK_TABLE")" || die "restored count failed: $DB"
  [[ "$SRC_COUNT" == "$DST_COUNT" ]] || die "row count changed during backup or restore: $DB"
  printf '%s_RESTORED_TABLES=%s\n' "$DB" "$COUNT"
done
( cd "$BACKUP" && sha256sum SHA256SUMS > SHA256SUMS.sha256 ) || die 'cannot seal verified backup manifest'
printf 'FRESH_BACKUP=RESTORE_VERIFIED\nSOURCE_PROJECT=mars-uat-fresh\nSOURCE_MASTER=mars_uat_master\nSOURCE_PERIOD=mars_uat_demo_2026\nVERIFIED_UTC=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$BACKUP/RESTORE_VERIFIED"
printf 'BACKUP_CHECKSUM=PASS\nOFFLINE_RESTORE=PASS\nORIGINAL_FRESH_DATABASES_MODIFIED=NO\nBACKUP_PATH=%s\n' "$BACKUP"
