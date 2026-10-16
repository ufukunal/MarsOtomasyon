#!/usr/bin/env bash
# Fresh UAT ONLY: isolated role synchronization with updated provisioner.
# Requires the CI-exported approved file at package root and a fresh restored backup.
set -Eeuo pipefail
umask 077
die(){ echo "BLOCKED: $*" >&2; exit 77; }
[[ "$EUID" != 0 ]] || die 'run as the ordinary ufuk user'
[[ "$#" == 0 ]] || die 'no arguments accepted'
SCRIPT_DIR="$(cd -- "$(dirname -- "$0")" && pwd -P)"
ROOT="$(cd -- "$SCRIPT_DIR/.." && pwd -P)"
PROVISIONER="$ROOT/app/Support/Auth/CompanyRoleProvisioner.php"
[[ -f "$PROVISIONER" && ! -L "$PROVISIONER" ]] || die 'updated provisioner absent'
grep -Fq "'contact_transfers.override_balance'" "$PROVISIONER" || die 'override permission absent in source'
grep -Fq "'contact_transfers.view'" "$PROVISIONER" || die 'contact transfer view permission absent in source'
for c in sudo docker grep sha256sum stat; do command -v "$c" >/dev/null || die "missing command: $c"; done
ENV="$HOME/.config/mars/uat-fresh.env"
COMPOSE="$HOME/mars-uat-fresh-src/compose.uat.fresh.yml"
[[ -f "$ENV" && ! -L "$ENV" && -f "$COMPOSE" && ! -L "$COMPOSE" ]] || die 'Fresh compose/env missing'
GATE="$SCRIPT_DIR/uat-fresh-identity-check.sh"
BACKUP_SCRIPT="$SCRIPT_DIR/uat-fresh-backup-verify.sh"
[[ -f "$GATE" && -f "$BACKUP_SCRIPT" ]] || die 'Fresh safety scripts absent'
bash "$GATE" || die 'Fresh identity preflight failed'
PG=mars-uat-fresh-postgres-1
WEB=mars-uat-fresh-web-1
BEFORE="$(sudo docker exec "$PG" psql -w -U mars_uat_owner -d mars_uat_master -XAtq -v ON_ERROR_STOP=1 -c "SELECT count(*) FROM permissions WHERE name IN ('contact_transfers.view','contact_transfers.create','contact_transfers.cancel','contact_transfers.override_balance') AND guard_name='web';")" || die 'Fresh master permissions preflight failed'
[[ "$BEFORE" =~ ^[0-4]$ ]] || die 'unexpected permissions count'
echo "FRESH_PERMISSIONS_BEFORE=$BEFORE"
echo '=== 22/27 Fresh master backup and disposable full restore ==='
BACKUP_LOG="$(mktemp)"
trap 'rm -f "$BACKUP_LOG"' EXIT
bash "$BACKUP_SCRIPT" | tee "$BACKUP_LOG" || die 'backup or offline restore failed; role synchronization blocked'
grep -qx 'OFFLINE_RESTORE=PASS' "$BACKUP_LOG" || die 'offline restore proof missing'
BACKUP="$(sed -n 's/^BACKUP_PATH=//p' "$BACKUP_LOG")"
[[ "$BACKUP" == "$HOME"/mars-fresh-backups/fresh-* && -f "$BACKUP/RESTORE_VERIFIED" ]] || die 'invalid backup path'
(cd "$BACKUP" && sha256sum -c --status SHA256SUMS.sha256 && sha256sum -c --status SHA256SUMS) || die 'backup integrity failed'
echo '=== 22/27 Isolated Fresh role synchronization ==='
# Mount only the updated provisioner, read-only. No running container replacement.
# The command is already present in the pinned Fresh image.
sudo docker compose --env-file "$ENV" -f "$COMPOSE" run --rm -T --no-deps \
  -v "$PROVISIONER:/var/www/html/app/Support/Auth/CompanyRoleProvisioner.php:ro" \
  --entrypoint php web artisan permissions:sync-company-roles --no-interaction \
  || die 'Fresh company role synchronization failed'
AFTER="$(sudo docker exec "$PG" psql -w -U mars_uat_owner -d mars_uat_master -XAtq -v ON_ERROR_STOP=1 -c "SELECT count(*) FROM permissions WHERE name IN ('contact_transfers.view','contact_transfers.create','contact_transfers.cancel','contact_transfers.override_balance') AND guard_name='web';")" || die 'Fresh permission postflight query failed'
[[ "$AFTER" == 4 ]] || die "incomplete permission creation ($AFTER/4)"
ROLE_COUNTS="$(sudo docker exec "$PG" psql -w -U mars_uat_owner -d mars_uat_master -XAtq -v ON_ERROR_STOP=1 -c "SELECT p.name || '=' || count(DISTINCT rp.role_id) FROM permissions p LEFT JOIN role_has_permissions rp ON rp.permission_id=p.id WHERE p.name IN ('contact_transfers.view','contact_transfers.create','contact_transfers.cancel','contact_transfers.override_balance') AND p.guard_name='web' GROUP BY p.name ORDER BY p.name;")" || die 'Fresh role assignment validation failed'
printf '%s\n' "$ROLE_COUNTS"
[[ "$(printf '%s\n' "$ROLE_COUNTS" | grep -cE '^contact_transfers\.[a-z_]+=[1-9][0-9]*$')" == 4 ]] || die 'permission assignments not complete'
[[ "$(sudo docker inspect "$WEB" --format '{{index .Config.Labels "com.docker.compose.project"}}')" == mars-uat-fresh ]] || die 'running Fresh web identity changed'
echo 'FRESH_22=PASS'
echo 'TARGET_DB=mars_uat_master'
echo 'PROD_TOUCHED=NO'
echo 'NEXT_GATE=23/27 Fresh web image rollout'
