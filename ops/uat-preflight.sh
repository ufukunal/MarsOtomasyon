#!/usr/bin/env bash
# Read-only policy check; never sources or echoes secret contents.
set -Eeuo pipefail
ENV_FILE="${1:-/etc/mars/uat.env}"
[[ -r "$ENV_FILE" && ! -L "$ENV_FILE" ]] || { echo "BLOCKED: UAT env not readable or is a symlink" >&2; exit 77; }
mode="$(stat -c '%a' "$ENV_FILE")"
[[ "$mode" == 600 || "$mode" == 640 ]] || { echo "BLOCKED: UAT env must have 0600 or 0640 permissions" >&2; exit 77; }
value() { sed -n -E "s/^${1}=(.*)$/\\1/p" "$ENV_FILE" | tail -n 1 | tr -d '\r'; }
must_equal() {
  if [[ "$(value "$1")" != "$2" ]]; then
    echo "BLOCKED: $1 fails UAT isolation policy" >&2; exit 77
  fi
}
must_equal MARS_UAT_ISOLATION_CONFIRMED YES
must_equal DB_USERNAME mars_test
must_equal REDIS_USERNAME mars_test
must_equal REDIS_PREFIX mars:test:
must_equal DB_CONNECTION master
must_equal QUEUE_CONNECTION redis
db="$(value DB_MASTER_DATABASE)"
[[ "$db" =~ ^mars_test_[A-Za-z0-9_]+$ ]] || { echo "BLOCKED: non-test DB name" >&2; exit 77; }
[[ -n "$(value DB_PASSWORD)" && -n "$(value REDIS_PASSWORD)" ]] || { echo "BLOCKED: missing test credentials" >&2; exit 77; }
key="$(value APP_KEY)"
[[ -n "$key" && "$key" != "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=" ]] || { echo "BLOCKED: unique UAT application key missing" >&2; exit 77; }
must_equal APP_DEBUG false
dbhost="$(value DB_HOST)"
redishost="$(value REDIS_HOST)"
[[ -n "$dbhost" && -n "$redishost" && "$dbhost" != "127.0.0.1" && "$redishost" != "127.0.0.1" ]] || { echo "BLOCKED: explicit external TEST services required" >&2; exit 77; }
echo "PASS: isolated UAT configuration policy. Network ACL and actual role privileges still need separate read-only verification."
