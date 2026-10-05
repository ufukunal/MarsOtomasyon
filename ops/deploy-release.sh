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

if [[ ! "${COMMIT_SHA}" =~ ^[a-fA-F0-9]{7,64}$ ]]; then
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
