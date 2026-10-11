#!/usr/bin/env bash
# Disposable UAT login preview. No PostgreSQL/Valkey credentials, migrations,
# workers or persistent application data. Not a production deployment.
set -Eeuo pipefail

SHA="${1:?usage: bash ops/uat-login-only-preview.sh <full-reviewed-git-sha>}"
if [[ ! "$SHA" =~ ^[a-f0-9]{40}$ ]]; then
  echo 'BLOCKED: a full lowercase 40-character commit SHA is required' >&2
  exit 77
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
cd "$ROOT"
if [[ "$(git rev-parse HEAD)" != "$SHA" ]] || [[ -n "$(git status --porcelain)" ]]; then
  echo 'BLOCKED: a clean checkout of the requested release commit is required' >&2
  exit 77
fi

for required in git docker sudo curl openssl grep; do
  command -v "$required" >/dev/null || { echo "BLOCKED: missing $required" >&2; exit 77; }
done

IMAGE="ghcr.io/ufukunal/marsotomasyon:uat-${SHA}"
CONTAINER=mars-uat-login-preview
NETWORK=mars-uat-preview-internal
PORT=18080

# Never replace an existing container or use an occupied host port.
if [[ -n "$(sudo docker ps -a --filter "name=^/${CONTAINER}$" --format '{{.ID}}')" ]]; then
  echo 'BLOCKED: preview container already exists; manual review required' >&2
  exit 77
fi
if command -v ss >/dev/null && ss -ltn "( sport = :${PORT} )" | grep -q 'LISTEN'; then
  echo "BLOCKED: local port $PORT is already listening" >&2
  exit 77
fi

# GHCR may be private on an unauthenticated host; build that exact clean
# checkout if pull is unavailable. Never pull a mutable latest tag.
if ! sudo docker image inspect "$IMAGE" >/dev/null 2>&1; then
  if ! sudo docker pull "$IMAGE"; then
    echo 'NOTICE: GHCR pull unavailable; building reviewed source locally.'
    sudo docker build --file Dockerfile.uat \
      --label "org.opencontainers.image.revision=${SHA}" \
      --tag "$IMAGE" .
  fi
fi
REVISION="$(sudo docker image inspect "$IMAGE" --format '{{ index .Config.Labels "org.opencontainers.image.revision" }}')"
if [[ "$REVISION" != "$SHA" ]]; then
  echo 'BLOCKED: image source revision differs from reviewed checkout' >&2
  exit 77
fi

# --internal prevents external container routing. Never attach this app to
# database/Valkey containers or to the host network.
if sudo docker network inspect "$NETWORK" >/dev/null 2>&1; then
  DRIVER="$(sudo docker network inspect "$NETWORK" --format '{{.Driver}}')"
  INTERNAL="$(sudo docker network inspect "$NETWORK" --format '{{.Internal}}')"
  if [[ "$DRIVER" != bridge || "$INTERNAL" != true ]]; then
    echo 'BLOCKED: existing preview network does not enforce internal bridge isolation' >&2
    exit 77
  fi
else
  sudo docker network create --driver bridge --internal "$NETWORK" >/dev/null
fi

APP_KEY="base64:$(openssl rand -base64 32)"
# Random key is never written to source, disk or log output. All data
# connections are disabled/unreachable and no credentials are supplied.
sudo docker run --detach --rm \
  --name "$CONTAINER" \
  --network "$NETWORK" \
  --publish "127.0.0.1:${PORT}:8080" \
  --security-opt no-new-privileges:true \
  --cap-drop ALL \
  --pids-limit 128 \
  --memory 512m --cpus 1 \
  --tmpfs /tmp:rw,nosuid,nodev,size=64m \
  -e APP_ENV=testing \
  -e APP_DEBUG=false \
  -e "APP_KEY=${APP_KEY}" \
  -e "APP_URL=http://127.0.0.1:${PORT}" \
  -e DB_CONNECTION=master \
  -e DB_HOST=192.0.2.1 \
  -e DB_PORT=9 \
  -e DB_MASTER_DATABASE=unavailable_offline_preview \
  -e DB_USERNAME=unavailable_offline_preview \
  -e REDIS_HOST=192.0.2.1 \
  -e REDIS_PORT=9 \
  -e CACHE_STORE=array \
  -e SESSION_DRIVER=array \
  -e QUEUE_CONNECTION=sync \
  -e MAIL_MAILER=array \
  "$IMAGE" >/dev/null
unset APP_KEY

page="$(mktemp)"
trap 'rm -f "$page"' EXIT
for attempt in $(seq 1 25); do
  if curl --fail --silent --max-time 3 "http://127.0.0.1:${PORT}/up" >/dev/null \
    && curl --fail --silent --max-time 5 "http://127.0.0.1:${PORT}/giris" > "$page" \
    && grep -Eiq '<form|wire:' "$page"; then
    echo 'PASS: local UAT login preview responds on /up and /giris.'
    echo "HOST_LOCAL_URL=http://127.0.0.1:${PORT}/giris"
    echo 'SSH_TUNNEL=local 18080 -> remote 127.0.0.1:18080'
    echo 'MODE=OFFLINE_LOGIN_ONLY; no user login or business data access.'
    exit 0
  fi
  sleep 2
done

echo 'BLOCKED: preview HTTP smoke failed; stopping only the preview container.' >&2
sudo docker stop "$CONTAINER" >/dev/null 2>&1 || true
exit 77
