#!/usr/bin/env bash
# Operator-invoked, isolated web-only UAT start. Never provisions DBs or queues.
set -Eeuo pipefail
EXPECTED_SHA="${1:?pass the reviewed full Git commit SHA}"
ENV_FILE="${MARS_UAT_ENV_FILE:-/etc/mars/uat.env}"
if [[ "$ENV_FILE" != /* ]]; then
  echo "BLOCKED: absolute UAT env path required" >&2
  exit 77
fi
export MARS_UAT_ENV_FILE="$ENV_FILE"  # Same path is loaded by Compose.
if [[ "${MARS_UAT_DEPLOY_APPROVED:-}" != "I_APPROVE_ISOLATED_UAT_WEB_ONLY" ]]; then
  echo "BLOCKED: explicit web-only UAT approval flag required" >&2
  exit 77
fi
if [[ ! "$EXPECTED_SHA" =~ ^[a-f0-9]{40}$ ]]; then
  echo "BLOCKED: full 40-character lowercase Git SHA required" >&2
  exit 77
fi

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
cd "$REPO_ROOT"
if [[ "$(git rev-parse HEAD)" != "$EXPECTED_SHA" ]] || ! git diff --quiet HEAD || ! git diff --cached --quiet; then
  echo "BLOCKED: checkout is not the reviewed clean release commit" >&2
  exit 77
fi

bash ops/uat-preflight.sh "$ENV_FILE"
command -v docker >/dev/null || { echo "BLOCKED: Docker missing" >&2; exit 77; }
docker info >/dev/null || { echo "BLOCKED: Docker is not accessible to this account" >&2; exit 77; }
docker compose version >/dev/null

PORT="${MARS_UAT_PORT:-18080}"
if [[ ! "$PORT" =~ ^[0-9]+$ ]] || ((PORT<1024 || PORT>65535)); then
  echo "BLOCKED: invalid local UAT port" >&2; exit 77
fi

# Never print env_file contents. All DB/Valkey values reach only the test image.
docker compose -f compose.uat.yml config --quiet
if [[ -n "${MARS_UAT_IMAGE:-}" ]]; then
  EXPECTED_IMAGE="ghcr.io/ufukunal/marsotomasyon:uat-${EXPECTED_SHA}"
  if [[ "$MARS_UAT_IMAGE" != "$EXPECTED_IMAGE" ]]; then
    echo "BLOCKED: requested OCI image does not match reviewed Git commit" >&2
    exit 77
  fi
  docker compose -f compose.uat.yml pull web
  # A tag alone is not a verifiable source-to-image match.
  IMAGE_REVISION="$(docker image inspect "$EXPECTED_IMAGE" --format '{{ index .Config.Labels "org.opencontainers.image.revision" }}')"
  if [[ "$IMAGE_REVISION" != "$EXPECTED_SHA" ]]; then
    echo "BLOCKED: pulled image revision does not match reviewed Git commit" >&2
    exit 77
  fi
else
  docker compose -f compose.uat.yml build web
fi

# Read-only remote checks: exact test PostgreSQL identities, no CREATEDB role,
# non-production CONNECT ACLs, isolated Valkey user. No data modifications.
docker compose -f compose.uat.yml run --rm -T --no-deps \
  --entrypoint php web scripts/ci/v4-remote-preflight.php

# Only now start the UAT web service. Never start worker/scheduler profiles.
docker compose -f compose.uat.yml up -d --no-deps web

# Verify the actual application response, not just Docker container state.
for attempt in $(seq 1 15); do
  if curl --silent --show-error --fail --max-time 3 "http://127.0.0.1:${PORT}/up" >/dev/null 2>&1 \
    && curl --silent --show-error --fail --max-time 3 "http://127.0.0.1:${PORT}/giris" >/dev/null 2>&1; then
    echo "PASS: isolated MarsProject UAT web is responding."
    echo "HOST_LOCAL_PREVIEW=http://127.0.0.1:${PORT}/giris"
    echo "ACCESS=SSH local port forward required; not publicly exposed."
    exit 0
  fi
  sleep 2
done

echo "BLOCKED: UAT web did not pass /up and /giris checks; stopping only the UAT web service." >&2
docker compose -f compose.uat.yml stop web >/dev/null 2>&1 || true
exit 77
