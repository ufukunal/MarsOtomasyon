#!/usr/bin/env bash
# Disposable synthetic fixture only: no Docker, PostgreSQL, or Valkey activity.
set -Eeuo pipefail
workspace="$(mktemp -d)"
trap 'rm -rf "$workspace"' EXIT
fixture="$workspace/uat.env"
cat > "$fixture" <<'ENV'
MARS_UAT_ISOLATION_CONFIRMED=YES
APP_KEY=base64:synthetic-test-key-not-for-production
APP_DEBUG=false
DB_CONNECTION=master
DB_HOST=mars-test.example.invalid
DB_MASTER_DATABASE=mars_test_master
DB_USERNAME=mars_test
DB_PASSWORD=synthetic_test_password
REDIS_HOST=mars-test.example.invalid
REDIS_USERNAME=mars_test
REDIS_PASSWORD=synthetic_test_password
REDIS_PREFIX=mars:test:
QUEUE_CONNECTION=redis
ENV
chmod 600 "$fixture"
bash ops/uat-preflight.sh "$fixture" > /dev/null
echo "PASS: synthetic isolated UAT configuration accepted"

must_reject() {
  local reason="$1"
  if bash ops/uat-preflight.sh "$fixture" > "$workspace/output" 2>&1; then
    echo "FAIL: unsafe configuration accepted ($reason)" >&2
    exit 1
  fi
  echo "PASS: $reason rejected"
}
sed -i 's/DB_MASTER_DATABASE=mars_test_master/DB_MASTER_DATABASE=MarsProject_Master/' "$fixture"
must_reject "production Master name"
sed -i 's/DB_MASTER_DATABASE=MarsProject_Master/DB_MASTER_DATABASE=mars_test_master/' "$fixture"
sed -i 's/MARS_UAT_ISOLATION_CONFIRMED=YES/MARS_UAT_ISOLATION_CONFIRMED=NO/' "$fixture"
must_reject "missing explicit isolation confirmation"
sed -i 's/MARS_UAT_ISOLATION_CONFIRMED=NO/MARS_UAT_ISOLATION_CONFIRMED=YES/' "$fixture"
sed -i 's/REDIS_USERNAME=mars_test/REDIS_USERNAME=default/' "$fixture"
must_reject "production or shared Valkey user"
sed -i 's/REDIS_USERNAME=default/REDIS_USERNAME=mars_test/' "$fixture"
sed -i 's/APP_DEBUG=false/APP_DEBUG=true/' "$fixture"
must_reject "debug mode"
