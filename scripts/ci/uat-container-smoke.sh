#!/usr/bin/env bash
# Runtime smoke only; never opens network connections or deploys any service.
set -Eeuo pipefail
IMAGE="${1:?disposable UAT Docker image required}"
case "$IMAGE" in
  mars-uat-ci:*) ;;
  *) echo "BLOCKED: unexpected image tag" >&2; exit 77 ;;
esac

docker run --rm --network none --cap-drop ALL --security-opt no-new-privileges \
  -e APP_ENV=testing \
  -e APP_DEBUG=false \
  -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
  -e DB_HOST=192.0.2.1 \
  -e DB_PORT=9 \
  -e REDIS_HOST=192.0.2.1 \
  -e REDIS_PORT=9 \
  -e SESSION_DRIVER=array \
  -e CACHE_STORE=array \
  -e QUEUE_CONNECTION=sync \
  "$IMAGE" sh -ec '
    test -f public/build/manifest.json
    test -f vendor/autoload.php
    php -r '\''foreach (["bcmath","mbstring","pdo_pgsql","redis","gd","intl","zip"] as $ext) { if (!extension_loaded($ext)) { fwrite(STDERR, "MISSING: ".$ext.PHP_EOL); exit(77); } } echo "All required UAT PHP extensions present".PHP_EOL;'\''
    php artisan route:list --json >/dev/null
    echo "PASS: Laravel routes bootstrap without DB or Valkey network"
  '
