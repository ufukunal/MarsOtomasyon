#!/usr/bin/env bash
# Build a source-pinned release artifact without PROD credentials or server writes.
set -Eeuo pipefail

COMMIT_SHA="${1:?40-character Git SHA required}"
ARTIFACT_DIR="${2:?new release artifact directory required}"

if [[ ! "$COMMIT_SHA" =~ ^[a-f0-9]{40}$ ]]; then
  echo "BLOCKED: expected a full lowercase commit SHA" >&2
  exit 2
fi

for cmd in git tar composer npm sha256sum find sort xargs; do
  command -v "$cmd" >/dev/null || { echo "BLOCKED: missing $cmd" >&2; exit 77; }
done

CURRENT_SHA="$(git rev-parse --verify HEAD)"
if [[ "$CURRENT_SHA" != "$COMMIT_SHA" ]]; then
  echo "BLOCKED: checkout HEAD does not match requested commit" >&2
  exit 77
fi

if ! git diff --quiet HEAD -- || ! git diff --cached --quiet; then
  echo "BLOCKED: tracked source files differ from the reviewed Git commit" >&2
  exit 77
fi

if [[ -e "$ARTIFACT_DIR" || -L "$ARTIFACT_DIR" ]]; then
  echo "BLOCKED: artifact destination already exists" >&2
  exit 77
fi

PARENT="$(dirname "$ARTIFACT_DIR")"
mkdir -p "$PARENT"
PARENT="$(cd "$PARENT" && pwd -P)"
FINAL_DIR="$PARENT/$(basename "$ARTIFACT_DIR")"
STAGING="$(mktemp -d "$PARENT/.mars-artifact.XXXXXXXX")"
trap 'rm -rf "$STAGING"' EXIT

git archive "$COMMIT_SHA" | tar -xf - -C "$STAGING"

(
  cd "$STAGING"
  # Run only against the disposable artifact tree, never the live app directory.
  APP_ENV=local composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress
  npm ci --ignore-scripts --no-audit --no-fund
  npm run build
  rm -rf node_modules

  [[ -f artisan && -f vendor/autoload.php && -f public/build/manifest.json ]] || {
    echo "BLOCKED: production runtime dependencies or Vite manifest are missing" >&2
    exit 77
  }
  [[ ! -e .env ]] || { echo "BLOCKED: release artifact may not contain .env" >&2; exit 77; }

  printf '%s\n' "$COMMIT_SHA" > .mars-release-commit
  find . -type f ! -name '.mars-artifact-checksums' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum > .mars-artifact-checksums
  sha256sum -c --status .mars-artifact-checksums
)

mv "$STAGING" "$FINAL_DIR"
trap - EXIT
echo "Artifact ready: $FINAL_DIR"
echo "Release source: $COMMIT_SHA"
