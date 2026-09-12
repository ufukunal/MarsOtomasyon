#!/usr/bin/env bash
set -Eeuo pipefail

TARGET_SHA="26fa400d6694c73a01afd9c8e6bd6e212d891a26"
ROOT="/opt/marsotomasyon"
ENV_FILE=".env.production"
COMPOSE_FILE="docker-compose.production.yml"
TAG="release-${TARGET_SHA:0:12}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP_DIR="$HOME/.mars-deploy-backups/${STAMP}-fast"

cd "$ROOT"
test -f "$ENV_FILE"
test -f "$COMPOSE_FILE"
mkdir -p "$BACKUP_DIR" "$HOME/.mars"
chmod 700 "$HOME/.mars-deploy-backups" "$BACKUP_DIR" "$HOME/.mars"

OLD_SHA="$(git rev-parse HEAD)"
OLD_APP_IMAGE="$(docker inspect -f '{{.Image}}' marsotomasyon-app-1)"
OLD_WEB_IMAGE="$(docker inspect -f '{{.Image}}' marsotomasyon-web-1)"
DB_BEFORE="$(docker inspect -f '{{.Id}}' marsotomasyon-postgres-1)"
VALKEY_BEFORE="$(docker inspect -f '{{.Id}}' marsotomasyon-valkey-1)"
ROLLBACK_TAG="rollback-${OLD_SHA:0:12}"

echo "OLD_SHA=$OLD_SHA"
echo "TARGET_SHA=$TARGET_SHA"
git diff > "$BACKUP_DIR/predeploy.patch" || true
cp "$ENV_FILE" "$BACKUP_DIR/env.production.snapshot"
chmod 600 "$BACKUP_DIR/env.production.snapshot"
printf '%s\n' "$OLD_SHA" > "$BACKUP_DIR/source-sha.txt"

echo 'SAFETY_DB_DUMP=start'
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T postgres \
  sh -lc 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' | gzip -1 > "$BACKUP_DIR/database.sql.gz"
test -s "$BACKUP_DIR/database.sql.gz"

docker image tag "$OLD_APP_IMAGE" "marsotomasyon-app:$ROLLBACK_TAG"
docker image tag "$OLD_WEB_IMAGE" "marsotomasyon-web:$ROLLBACK_TAG"

git fetch --prune origin main
git cat-file -e "$TARGET_SHA^{commit}"
git checkout -q main
git reset --hard "$TARGET_SHA" >/dev/null
test "$(git rev-parse HEAD)" = "$TARGET_SHA"

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config --quiet
MARS_IMAGE_TAG="$TAG" docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" build app web

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T app php artisan down --retry=120 >/dev/null || true
MARS_IMAGE_TAG="$TAG" docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm --no-deps app php artisan migrate --force
MARS_IMAGE_TAG="$TAG" docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm --no-deps app php artisan optimize
MARS_IMAGE_TAG="$TAG" docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-deps --force-recreate app worker scheduler web

for attempt in $(seq 1 60); do
  if curl -fsS --max-time 3 http://127.0.0.1:8080/login >/dev/null; then
    break
  fi
  if [ "$attempt" -eq 60 ]; then
    echo 'HTTP_HEALTH=fail' >&2
    MARS_IMAGE_TAG="$ROLLBACK_TAG" docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-deps --force-recreate app worker scheduler web || true
    git reset --hard "$OLD_SHA" >/dev/null || true
    exit 31
  fi
  sleep 2
done

MARS_IMAGE_TAG="$TAG" docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T app php artisan up >/dev/null || true

PORT="$(MARS_IMAGE_TAG="$TAG" docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" port web 80 | tail -n1)"
test "$PORT" = '127.0.0.1:8080'
test "$DB_BEFORE" = "$(docker inspect -f '{{.Id}}' marsotomasyon-postgres-1)"
test "$VALKEY_BEFORE" = "$(docker inspect -f '{{.Id}}' marsotomasyon-valkey-1)"
test "$(git rev-parse HEAD)" = "$TARGET_SHA"

docker exec marsotomasyon-web-1 nginx -v 2>&1 | grep -Eq 'nginx/1\.28\.'
MARS_IMAGE_TAG="$TAG" docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T app php artisan mars:ops-status | tee "$BACKUP_DIR/ops-status.json"
grep -q '"healthy": true' "$BACKUP_DIR/ops-status.json"

docker image tag "marsotomasyon-app:$TAG" marsotomasyon-app:current
docker image tag "marsotomasyon-web:$TAG" marsotomasyon-web:current
printf '%s\n' "$TARGET_SHA" > "$HOME/.mars/deployed-sha"
chmod 600 "$HOME/.mars/deployed-sha"

echo "DEPLOYED_SHA=$(git rev-parse HEAD)"
echo "WEB_BIND=$PORT"
echo 'DATA_SERVICES_UNCHANGED=yes'
echo -n 'NGINX_VERSION='; docker exec marsotomasyon-web-1 nginx -v 2>&1
echo 'PRODUCTION_DEPLOY=pass'
