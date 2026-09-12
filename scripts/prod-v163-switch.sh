#!/usr/bin/env bash
set -Eeuo pipefail

ROOT=/opt/marsotomasyon
RELEASE=6e5a91b74f1bac3fede949f8479b17367e04c71c
TAG=release-${RELEASE:0:12}
REL="$ROOT/.mars-releases/$RELEASE"
ENV="$ROOT/.env.production"
ROLLBACK_TAG=rollback-pre-v163-$(date -u +%Y%m%dT%H%M%SZ)

export MARS_ENV_FILE="$ENV"
COMPOSE=(docker compose --env-file "$ENV" -f "$REL/docker-compose.production.yml")

fail() {
  echo "ERROR: $*" >&2
  exit 1
}

[ -d "$REL" ] || fail "release worktree missing: $REL"
[ -f "$ENV" ] || fail "production env missing: $ENV"
[ "$(git -C "$REL" rev-parse HEAD)" = "$RELEASE" ] || fail "release SHA mismatch"
[ -z "$(git -C "$REL" status --porcelain)" ] || fail "release worktree is dirty"
docker image inspect "marsotomasyon-app:$TAG" >/dev/null || fail "target app image missing"
docker image inspect "marsotomasyon-web:$TAG" >/dev/null || fail "target web image missing"

APP_CID="$(${COMPOSE[@]} ps -q app)"
WORKER_CID="$(${COMPOSE[@]} ps -q worker)"
SCHED_CID="$(${COMPOSE[@]} ps -q scheduler)"
WEB_CID="$(${COMPOSE[@]} ps -q web)"
[ -n "$APP_CID" ] || fail "current app container missing"
[ -n "$WORKER_CID" ] || fail "current worker container missing"
[ -n "$SCHED_CID" ] || fail "current scheduler container missing"
[ -n "$WEB_CID" ] || fail "current web container missing"

OLD_APP_ID="$(docker inspect "$APP_CID" --format '{{.Image}}')"
OLD_WEB_ID="$(docker inspect "$WEB_CID" --format '{{.Image}}')"
docker image tag "$OLD_APP_ID" "marsotomasyon-app:$ROLLBACK_TAG"
docker image tag "$OLD_WEB_ID" "marsotomasyon-web:$ROLLBACK_TAG"

echo "TARGET_RELEASE=$RELEASE"
echo "TARGET_TAG=$TAG"
echo "ROLLBACK_TAG=$ROLLBACK_TAG"
echo "PRE_DEPLOY_ROOT_SHA=$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || echo unavailable)"
echo 'PRE_DEPLOY_ROOT_STATUS_BEGIN'
git -C "$ROOT" status --porcelain 2>/dev/null || true
echo 'PRE_DEPLOY_ROOT_STATUS_END'

do_rollback() {
  rc=$?
  trap - ERR
  echo "ROLLBACK_BEGIN rc=$rc tag=$ROLLBACK_TAG"
  export MARS_IMAGE_TAG="$ROLLBACK_TAG"
  "${COMPOSE[@]}" up -d --no-build --no-deps app worker scheduler web </dev/null || true
  "${COMPOSE[@]}" ps || true
  "${COMPOSE[@]}" exec -T app php artisan mars:ops-status </dev/null || true
  echo "ROLLBACK_END tag=$ROLLBACK_TAG"
  exit "$rc"
}
trap do_rollback ERR

export MARS_IMAGE_TAG="$TAG"
"${COMPOSE[@]}" up -d --no-build --no-deps app worker scheduler web </dev/null

for _ in $(seq 1 30); do
  APP_CID="$(${COMPOSE[@]} ps -q app)"
  WORKER_CID="$(${COMPOSE[@]} ps -q worker)"
  SCHED_CID="$(${COMPOSE[@]} ps -q scheduler)"
  WEB_CID="$(${COMPOSE[@]} ps -q web)"
  if [ -n "$APP_CID" ] && [ -n "$WORKER_CID" ] && [ -n "$SCHED_CID" ] && [ -n "$WEB_CID" ] \
    && [ "$(docker inspect "$APP_CID" --format '{{.State.Running}}')" = true ] \
    && [ "$(docker inspect "$WORKER_CID" --format '{{.State.Running}}')" = true ] \
    && [ "$(docker inspect "$SCHED_CID" --format '{{.State.Running}}')" = true ] \
    && [ "$(docker inspect "$WEB_CID" --format '{{.State.Running}}')" = true ]; then
    break
  fi
  sleep 2
done

APP_CID="$(${COMPOSE[@]} ps -q app)"
WORKER_CID="$(${COMPOSE[@]} ps -q worker)"
SCHED_CID="$(${COMPOSE[@]} ps -q scheduler)"
WEB_CID="$(${COMPOSE[@]} ps -q web)"
TARGET_APP_ID="$(docker image inspect "marsotomasyon-app:$TAG" --format '{{.Id}}')"
TARGET_WEB_ID="$(docker image inspect "marsotomasyon-web:$TAG" --format '{{.Id}}')"

[ "$(docker inspect "$APP_CID" --format '{{.Image}}')" = "$TARGET_APP_ID" ] || fail "app image did not switch"
[ "$(docker inspect "$WORKER_CID" --format '{{.Image}}')" = "$TARGET_APP_ID" ] || fail "worker image did not switch"
[ "$(docker inspect "$SCHED_CID" --format '{{.Image}}')" = "$TARGET_APP_ID" ] || fail "scheduler image did not switch"
[ "$(docker inspect "$WEB_CID" --format '{{.Image}}')" = "$TARGET_WEB_ID" ] || fail "web image did not switch"

"${COMPOSE[@]}" exec -T app php artisan optimize </dev/null
"${COMPOSE[@]}" exec -T app php artisan mars:ops-status </dev/null

LOGIN_CODE="$(curl -sS -o /tmp/mars-v163-login.html -w '%{http_code}' http://127.0.0.1:8080/login)"
[ "$LOGIN_CODE" = 200 ] || fail "login HTTP status is $LOGIN_CODE"
grep -Eq 'Oturum Aç|Giriş|login' /tmp/mars-v163-login.html || fail "login page marker missing"
rm -f /tmp/mars-v163-login.html

DASH_CODE="$(curl -sS -o /dev/null -w '%{http_code}' http://127.0.0.1:8080/dashboard)"
case "$DASH_CODE" in
  200|301|302|303|307|308) ;;
  *) fail "dashboard HTTP status is $DASH_CODE" ;;
esac

POSTGRES_CID="$(${COMPOSE[@]} ps -q postgres)"
VALKEY_CID="$(${COMPOSE[@]} ps -q valkey)"
[ -n "$POSTGRES_CID" ] || fail "postgres container missing"
[ -n "$VALKEY_CID" ] || fail "valkey container missing"
[ "$(docker inspect "$POSTGRES_CID" --format '{{.State.Running}}')" = true ] || fail "postgres not running"
[ "$(docker inspect "$VALKEY_CID" --format '{{.State.Running}}')" = true ] || fail "valkey not running"
[ "$(docker inspect "$POSTGRES_CID" --format '{{if .State.Health}}{{.State.Health.Status}}{{end}}')" = healthy ] || fail "postgres not healthy"
[ "$(docker inspect "$VALKEY_CID" --format '{{if .State.Health}}{{.State.Health.Status}}{{end}}')" = healthy ] || fail "valkey not healthy"

trap - ERR
printf '%s\n' "$RELEASE" > "$ROOT/.mars-production-release"

echo "PRODUCTION_RELEASE=$RELEASE"
echo "PRODUCTION_TAG=$TAG"
echo "LOGIN_HTTP=$LOGIN_CODE"
echo "DASHBOARD_HTTP=$DASH_CODE"
echo "APP_IMAGE_ID=$TARGET_APP_ID"
echo "WEB_IMAGE_ID=$TARGET_WEB_ID"
echo "POSTGRES_HEALTH=healthy"
echo "VALKEY_HEALTH=healthy"
echo 'PRODUCTION_SERVICES_BEGIN'
"${COMPOSE[@]}" ps
echo 'PRODUCTION_SERVICES_END'
