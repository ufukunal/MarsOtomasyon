#!/usr/bin/env bash
# Run on CI host only; no GitHub CLI on PROD.
set -Eeuo pipefail
umask 077
fail(){ echo "BLOCKED: $*" >&2; exit 77; }
for c in git tar gzip sha256sum; do command -v "$c" >/dev/null || fail "missing: $c"; done
ROOT="$(git rev-parse --show-toplevel)" || fail 'not a Git checkout'
cd "$ROOT"
[[ "$(git status --porcelain --untracked-files=no)" == '' ]] || fail 'tracked files have modifications'
SHA="$(git rev-parse HEAD)"
[[ "$SHA" =~ ^[0-9a-f]{40}$ ]] || fail 'invalid source SHA'
if [[ -n "\${1:-}" ]]; then [[ "$1" == "$SHA" ]] || fail 'checkout does not match requested SHA'; fi
FILES=(
 ops/uat-fresh-stage-18-21.sh
 ops/uat-fresh-identity-check.sh
 ops/uat-fresh-backup-verify.sh
 ops/uat-fresh-period-migrations.sh
 database/migrations/period/0001_12_02_000010_add_document_additional_discount.php
 database/migrations/period/0001_12_02_000020_create_contact_transfers.php
)
for p in "\${FILES[@]}"; do [[ -f "$p" ]] || fail "missing: $p"; done
DEST="\${MARS_FRESH_PACKAGE_DIR:-$HOME/mars-fresh-package}"
[[ ! -L "$DEST" ]] || fail 'symlinked output directory'
mkdir -p "$DEST"; chmod 700 "$DEST"
ARCHIVE="$DEST/mars-fresh-18-21-$SHA.tar.gz"
[[ ! -e "$ARCHIVE" ]] || fail 'refuse overwrite'
git archive --format=tar HEAD -- "\${FILES[@]}" | gzip -n -9 > "$ARCHIVE"
[[ -s "$ARCHIVE" ]] || fail 'empty archive'
tar -tzf "$ARCHIVE" >/dev/null || fail 'invalid release archive'
(cd "$DEST" && sha256sum "$(basename "$ARCHIVE")" > "$(basename "$ARCHIVE").sha256")
printf 'PACKAGE=%s\nSHA256_FILE=%s.sha256\nSOURCE_COMMIT=%s\n' "$ARCHIVE" "$ARCHIVE" "$SHA"
echo 'TRANSFER=copy the package and SHA256 file from CI to PROD; no GitHub access required on PROD'
