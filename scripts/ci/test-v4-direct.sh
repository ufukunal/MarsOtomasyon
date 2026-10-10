#!/usr/bin/env bash
# TEST V4 direct Tailscale clients for existing PROD VM PostgreSQL and Valkey.
set -Eeuo pipefail
suite="${1:-preflight}"
case "$suite" in preflight|contracts|unit|feature|integration|valkey|performance) ;; *) echo "BLOCKED: invalid V4 suite" >&2; exit 2 ;; esac
[[ "${RUNNER_NAME:-}" == mars-ubuntu-04 ]] || { echo "BLOCKED: unexpected runner" >&2; exit 77; }
[[ "$(hostname | tr '[:upper:]' '[:lower:]')" != *prod* ]] || { echo "BLOCKED: production runner" >&2; exit 77; }
if [[ "$suite" != preflight && "${ISOLATION_CONFIRMED:-NO}" != YES ]]; then
  echo "BLOCKED: operator must independently verify test DB and Valkey ACL isolation" >&2; exit 77
fi
host=mars-prod-1.taila20365.ts.net
[[ "${DB_HOST:-}" == "$host" && "${DB_PORT:-}" == 55432 && "${DB_USERNAME:-}" == mars_test && "${DB_MASTER_DATABASE:-}" == mars_test_master ]] || {
  echo "BLOCKED: invalid PostgreSQL test endpoint" >&2; exit 77;
}
[[ "${REDIS_HOST:-}" == "$host" && "${REDIS_PORT:-}" == 6379 && "${REDIS_USERNAME:-}" == mars_test && "${REDIS_PREFIX:-}" == mars:test: ]] || {
  echo "BLOCKED: invalid Valkey test endpoint" >&2; exit 77;
}
for command_name in php getent tailscale timeout; do
  command -v "$command_name" >/dev/null || { echo "BLOCKED: missing $command_name client" >&2; exit 77; }
done
timeout 5 tailscale status >/dev/null 2>&1 || { echo "BLOCKED: Tailscale unavailable" >&2; exit 77; }
addr="$(getent ahostsv4 "$host" | awk 'NR==1{print $1}')"
[[ -n "$addr" ]] || { echo "BLOCKED: Tailscale DNS unresolved" >&2; exit 77; }
php -r '
  $value=ip2long($argv[1]); $lower=ip2long("100.64.0.0"); $upper=ip2long("100.127.255.255");
  exit($value !== false && $value >= $lower && $value <= $upper ? 0 : 77);
' "$addr" || { echo "BLOCKED: resolved host is outside Tailnet address range" >&2; exit 77; }
php -r 'exit(PHP_VERSION_ID >= 80400 && extension_loaded("pdo_pgsql") && extension_loaded("redis") ? 0 : 77);' || {
  echo "BLOCKED: PHP >=8.4 with pgsql/Valkey client extensions required" >&2; exit 77;
}
php scripts/ci/v4-remote-preflight.php || exit 77
echo "V4 dedicated test database and Valkey ACL identities verified read-only"
if [[ "$suite" == preflight ]]; then
  echo "Read-only preflight done; no migrations, data writes or V4 tests executed."
  exit 0
fi
export MARS_V4_REMOTE_TEST_READY=YES
bash scripts/ci/test-v4.sh "$suite"
