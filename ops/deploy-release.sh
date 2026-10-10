#!/usr/bin/env bash
set -euo pipefail

RELEASE_ID="${1:?release_id gerekli}"
COMMIT_SHA="${2:?commit_sha gerekli}"
ARTIFACT_DIR="${3:?hazir artifact dizini gerekli}"
PREVIOUS_RELEASE_ID="${4:-}"

MARS_ROOT="${MARS_ROOT:-/var/www/mars}"
RELEASES_ROOT="${MARS_ROOT}/releases"
CURRENT_LINK="${MARS_ROOT}/current"
SHARED_ROOT="${MARS_ROOT}/shared"
RELEASE_PATH="${RELEASES_ROOT}/${RELEASE_ID}"

if [[ ! "${RELEASE_ID}" =~ ^[A-Za-z0-9._-]+$ ]]; then
  echo "Gecersiz release_id" >&2
  exit 2
fi

if [[ ! "${COMMIT_SHA}" =~ ^[a-f0-9]{40}$ ]]; then
  echo "Gecersiz commit SHA" >&2
  exit 2
fi

if [[ ! -f "${ARTIFACT_DIR}/artisan" ]]; then
  echo "Artifact Laravel release degil: artisan bulunamadi" >&2
  exit 2
fi

if [[ -e "${RELEASE_PATH}" ]]; then
  echo "Immutable release zaten mevcut: ${RELEASE_PATH}" >&2
  exit 2
fi

if [[ ! -f "${ARTIFACT_DIR}/.mars-release-commit" || ! -f "${ARTIFACT_DIR}/.mars-artifact-checksums" ]]; then
  echo "BLOCKED: release provenance and integrity manifest required" >&2
  exit 77
fi

ARTIFACT_COMMIT="$(< "${ARTIFACT_DIR}/.mars-release-commit")"
if [[ "$ARTIFACT_COMMIT" != "$COMMIT_SHA" ]]; then
  echo "BLOCKED: release artifact commit does not match requested SHA" >&2
  exit 77
fi

if [[ -e "${ARTIFACT_DIR}/.env" ]]; then
  echo "BLOCKED: secret .env must not be embedded in release artifact" >&2
  exit 77
fi

if ! (cd "$ARTIFACT_DIR" && sha256sum -c --status .mars-artifact-checksums); then
  echo "BLOCKED: release artifact checksum verification failed" >&2
  exit 77
fi

# The lock is acquired after all read-only artifact checks, before any release,
# migration, symlink or service mutation. Do not allow overlapping deployments.
command -v flock >/dev/null || { echo "BLOCKED: flock is required for serialized deployment" >&2; exit 77; }
mkdir -p "${MARS_ROOT}"
exec 9>"${MARS_ROOT}/.mars-deploy.lock"
if ! flock -n 9; then
  echo "BLOCKED: another release is already deploying" >&2
  exit 77
fi

mkdir -p "${RELEASES_ROOT}" "${SHARED_ROOT}/storage"
mkdir "${RELEASE_PATH}"

rsync -a --delete   --exclude='.env'   --exclude='storage'   "${ARTIFACT_DIR}/" "${RELEASE_PATH}/"

ln -s "${SHARED_ROOT}/storage" "${RELEASE_PATH}/storage"
ln -s "/etc/mars/mars.env" "${RELEASE_PATH}/.env"

cd "${RELEASE_PATH}"

if [[ ! -d vendor ]]; then
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
fi

if [[ -f package.json && ! -d public/build ]]; then
  npm ci
  npm run build
fi

ARGS=(
  operations:deploy
  "--release=${RELEASE_ID}"
  "--commit=${COMMIT_SHA}"
  "--release-path=${RELEASE_PATH}"
  "--current-link=${CURRENT_LINK}"
)

if [[ -n "${PREVIOUS_RELEASE_ID}" ]]; then
  ARGS+=("--previous=${PREVIOUS_RELEASE_ID}")
fi

(
  set -a
  source /etc/mars/mars-operations.env
  set +a
  php artisan "${ARGS[@]}"
)

cd "${CURRENT_LINK}"
php artisan operations:health --no-persist
php artisan operations:smoke

echo "Release aktif ve post-activation health/smoke basarili: ${RELEASE_ID}"
