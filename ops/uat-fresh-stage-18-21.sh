#!/usr/bin/env bash
# One-pass Fresh UAT rollout gates: identity -> backup -> independent restore -> two period migrations.
# No production access, no web redeploy, no automatic rollback on failed migration.
set -Eeuo pipefail
umask 077
abort(){ echo "BLOCKED: $*" >&2; exit 77; }
[[ "$EUID" -ne 0 ]] || abort 'do not execute as root'
SCRIPT_DIR="$(cd -- "$(dirname -- "$0")" && pwd -P)"
for name in uat-fresh-identity-check.sh uat-fresh-backup-verify.sh uat-fresh-period-migrations.sh; do
  [[ -f "$SCRIPT_DIR/$name" && ! -L "$SCRIPT_DIR/$name" ]] || abort "missing trusted script $name"
done
[[ "$#" -eq 0 ]] || abort 'no parameters accepted'
echo '=== 18/27 Fresh identity verification ==='
bash "$SCRIPT_DIR/uat-fresh-identity-check.sh" || abort '18/27 failed, no backup/migration attempted'
echo '=== 19/27 Fresh backup and disposable offline restore ==='
# Capture only the backup's trusted output path, not any database password.
BACKUP_LOG="$(mktemp)"
trap 'rm -f "$BACKUP_LOG"' EXIT
bash "$SCRIPT_DIR/uat-fresh-backup-verify.sh" | tee "$BACKUP_LOG" || abort '19/27 failed; migration blocked'
grep -qx 'OFFLINE_RESTORE=PASS' "$BACKUP_LOG" || abort 'restore was not verified'
BACKUP_PATH="$(sed -n 's/^BACKUP_PATH=//p' "$BACKUP_LOG")"
[[ -n "$BACKUP_PATH" && "$(grep -c '^BACKUP_PATH=' "$BACKUP_LOG")" -eq 1 ]] || abort 'backup proof path missing or ambiguous'
echo '=== 20/27 + 21/27 approved period migrations ==='
bash "$SCRIPT_DIR/uat-fresh-period-migrations.sh" "$BACKUP_PATH" || abort 'Fresh period migration failed; retain backup, investigate before further changes'
echo 'FRESH_18_21=PASS'
echo 'NEXT_GATE=22/27 permission synchronization'
