#!/usr/bin/env bash
# Negative release tests: never connects to PROD or invokes deployment services.
set -Eeuo pipefail

ROOT="$(mktemp -d)"
trap 'rm -rf "$ROOT"' EXIT

SHA_A="$(printf 'a%.0s' {1..40})"
SHA_B="$(printf 'b%.0s' {1..40})"
ARTIFACT="$ROOT/artifact"
RUNTIME="$ROOT/not-allowed-to-exist"
mkdir -p "$ARTIFACT"
printf '#!/usr/bin/env php\n' > "$ARTIFACT/artisan"

assert_blocked_without_side_effects() {
  local reason="$1"
  if MARS_ROOT="$RUNTIME" bash ops/deploy-release.sh "test-release" "$SHA_A" "$ARTIFACT" > "$ROOT/output" 2>&1; then
    echo "FAIL: unsafe release accepted ($reason)" >&2
    exit 1
  fi
  if [[ -e "$RUNTIME" || -L "$RUNTIME" ]]; then
    echo "FAIL: blocked release changed application directories ($reason)" >&2
    exit 1
  fi
  echo "PASS: $reason"
}

assert_blocked_without_side_effects "missing provenance and manifest"

printf '%s\n' "$SHA_B" > "$ARTIFACT/.mars-release-commit"
(cd "$ARTIFACT" && sha256sum artisan .mars-release-commit > .mars-artifact-checksums)
assert_blocked_without_side_effects "commit mismatch"

printf '%s\n' "$SHA_A" > "$ARTIFACT/.mars-release-commit"
assert_blocked_without_side_effects "modified commit file against integrity manifest"

(cd "$ARTIFACT" && sha256sum artisan .mars-release-commit > .mars-artifact-checksums)
printf 'tampered artifact\n' >> "$ARTIFACT/artisan"
assert_blocked_without_side_effects "modified application file"

printf '#!/usr/bin/env php\n' > "$ARTIFACT/artisan"
(cd "$ARTIFACT" && sha256sum artisan .mars-release-commit > .mars-artifact-checksums)
printf 'APP_KEY=should-not-ship\n' > "$ARTIFACT/.env"
assert_blocked_without_side_effects "embedded production secrets"

rm "$ARTIFACT/.env"
rm "$ARTIFACT/.mars-artifact-checksums"
assert_blocked_without_side_effects "missing checksum list"
