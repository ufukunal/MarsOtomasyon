#!/usr/bin/env bash
# Read-only HTTP checks on an ephemeral loopback-only Laravel server.
set -Eeuo pipefail
[[ "${APP_ENV:-}" == testing && "${DB_MASTER_DATABASE:-}" == MarsProject_Master_Test_R1 ]] || {
  echo 'FAIL: only the isolated R1 database is allowed' >&2
  exit 41
}
command -v curl >/dev/null
port=18777
base="http://127.0.0.1:${port}"
log="${RUNNER_TEMP:-/tmp}/mars-v2-http-smoke-${GITHUB_RUN_ID:-manual}.log"
php artisan serve --host=127.0.0.1 --port="$port" --no-reload > "$log" 2>&1 &
server_pid=$!
cleanup() {
  kill "$server_pid" 2>/dev/null || true
  wait "$server_pid" 2>/dev/null || true
}
trap cleanup EXIT
ready=false
for _ in $(seq 1 60); do
  if curl --noproxy '*' --connect-timeout 1 --max-time 2 -fsS "$base/up" >/dev/null 2>&1; then
    ready=true
    break
  fi
  kill -0 "$server_pid" 2>/dev/null || { echo 'Local web server exited before readiness' >&2; exit 2; }
  sleep 0.3
done
[[ "$ready" == true ]] || { echo 'Health endpoint did not return HTTP 2xx' >&2; exit 3; }
status=$(curl --noproxy '*' -sS --max-time 10 -o /dev/null -w '%{http_code}' "$base/satis/siparisler")
[[ "$status" == '302' ]] || { echo "Anonymous sales order route: expected 302, got $status" >&2; exit 4; }
headers=$(curl --noproxy '*' -sS --max-time 10 -D - -o /dev/null "$base/satis/siparisler")
if ! printf '%s\n' "$headers" | grep -Eiq '^location: .*\/giris(\?|\r?$)'; then
  echo 'Anonymous protected route did not redirect to /giris' >&2
  exit 5
fi
echo 'Read-only /up health and anonymous /satis/siparisler authorization checks passed.'