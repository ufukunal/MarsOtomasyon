#!/usr/bin/env bash
# V4 runner is a client only: no local PostgreSQL/Valkey server or Docker dependency.
# Forward isolated connections to the pre-existing services on the PROD VM.
set -Eeuo pipefail

suite="${1:-preflight}"
case "$suite" in preflight|contracts|unit|feature|integration|valkey|performance) ;; *) echo "Unsupported V4 suite" >&2; exit 2 ;; esac
if [[ "$suite" != preflight && "${ISOLATION_CONFIRMED:-NO}" != YES ]]; then
  echo "BLOCKED: operator has not confirmed isolated test databases and Valkey ACL" >&2
  exit 77
fi
if [[ "${RUNNER_NAME:-}" != mars-ubuntu-04 ]]; then
  echo "BLOCKED: unexpected GitHub runner" >&2
  exit 77
fi
if [[ "$(hostname | tr '[:upper:]' '[:lower:]')" == *prod* ]]; then
  echo "BLOCKED: tests cannot run on a production host" >&2
  exit 77
fi
for executable in ssh php timeout; do
  command -v "$executable" >/dev/null || { echo "BLOCKED: missing client tool: $executable" >&2; exit 77; }
done
php -r 'exit(PHP_VERSION_ID >= 80400 && extension_loaded("pdo_pgsql") && extension_loaded("redis") ? 0 : 77);' || {
  echo "BLOCKED: PHP 8.4+, pdo_pgsql and phpredis CLIENT extensions are required" >&2
  exit 77
}
host="${MARS_V4_SSH_HOST:-192.168.239.132}"
port="${MARS_V4_SSH_PORT:-10000}"
user="${MARS_V4_SSH_USER:-ufuk}"
pg_port="${MARS_V4_REMOTE_PG_PORT:-55432}"
redis_port="${MARS_V4_REMOTE_REDIS_PORT:-6379}"
[[ "$host" == 192.168.239.132 || "$host" == mars-prod-1.taila20365.ts.net ]] || {
  echo "BLOCKED: unexpected PROD VM address" >&2; exit 77;
}
[[ "$port" == 10000 && "$user" == ufuk && "$pg_port" == 55432 && "$redis_port" == 6379 ]] || {
  echo "BLOCKED: SSH or service ports do not match the approved topology" >&2; exit 77;
}
[[ "${DB_HOST:-}" == 127.0.0.1 && "${DB_PORT:-}" == 5432 && "${REDIS_HOST:-}" == 127.0.0.1 && "${REDIS_PORT:-}" == 6379 ]] || {
  echo "BLOCKED: application must use localhost SSH forwards, not direct PROD sockets" >&2; exit 77;
}

tmp="$(mktemp -d)"
chmod 700 "$tmp"
tunnel_pid=""
cleanup() {
  if [[ -n "$tunnel_pid" ]]; then
    kill "$tunnel_pid" 2>/dev/null || true
    wait "$tunnel_pid" 2>/dev/null || true
  fi
  rm -rf "$tmp"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

ssh_opts=(-p "$port" -o StrictHostKeyChecking=yes -o ConnectTimeout=8 -o ConnectionAttempts=1
  -o ServerAliveInterval=10 -o ServerAliveCountMax=2 -o ExitOnForwardFailure=yes
  -o ClearAllForwardings=yes -o LogLevel=ERROR)
if [[ -n "${MARS_V4_SSH_KNOWN_HOSTS:-}" ]]; then
  printf '%s\n' "$MARS_V4_SSH_KNOWN_HOSTS" > "$tmp/known_hosts"
  chmod 600 "$tmp/known_hosts"
  ssh_opts+=(-o UserKnownHostsFile="$tmp/known_hosts")
fi
ssh_cmd=(ssh)
if [[ -n "${MARS_V4_SSH_PRIVATE_KEY:-}" ]]; then
  printf '%s\n' "$MARS_V4_SSH_PRIVATE_KEY" > "$tmp/id_key"
  chmod 600 "$tmp/id_key"
  ssh_opts+=(-i "$tmp/id_key" -o IdentitiesOnly=yes -o BatchMode=yes)
elif [[ -n "${MARS_V4_SSH_PASSWORD:-}" ]]; then
  command -v sshpass >/dev/null || { echo "BLOCKED: sshpass CLIENT required for password-based SSH" >&2; exit 77; }
  export SSHPASS="$MARS_V4_SSH_PASSWORD"
  ssh_cmd=(sshpass -e ssh)
  ssh_opts+=(-o BatchMode=no -o PubkeyAuthentication=no -o PreferredAuthentications=password)
else
  ssh_opts+=(-o BatchMode=yes -o PasswordAuthentication=no)
fi

target="$user@$host"
remote_name="$(timeout 20 "${ssh_cmd[@]}" "${ssh_opts[@]}" "$target" hostname)" || {
  echo "BLOCKED: PROD VM SSH authentication/host-key verification failed" >&2; exit 77;
}
[[ "$remote_name" == ufukmarsprod ]] || {
  echo "BLOCKED: remote VM hostname does not match the verified infrastructure contract" >&2; exit 77;
}
# SSH local forwards are bound only to runner loopback; no PostgreSQL/Valkey daemon is started.
"${ssh_cmd[@]}" "${ssh_opts[@]}" -N -T   -L 127.0.0.1:5432:127.0.0.1:55432   -L 127.0.0.1:6379:127.0.0.1:6379   "$target" &
tunnel_pid="$!"
for attempt in 1 2 3 4 5 6 7 8 9 10; do
  kill -0 "$tunnel_pid" 2>/dev/null || { echo "BLOCKED: SSH tunnel stopped" >&2; exit 77; }
  if php -r 'foreach ([5432,6379] as $p) { $c=@fsockopen("127.0.0.1",$p,$n,$s,1); if (!$c) exit(1); fclose($c); }' 2>/dev/null; then
    break
  fi
  sleep 1
done
kill -0 "$tunnel_pid" 2>/dev/null || { echo "BLOCKED: SSH tunnel unavailable" >&2; exit 77; }
php scripts/ci/v4-remote-preflight.php || exit 77
echo "V4 safe infrastructure preflight PASSED (two isolated PostgreSQL DBs, dedicated Valkey ACL via SSH tunnel)"
if [[ "$suite" == preflight ]]; then
  echo "Read-only preflight complete; no tests, database migrations or Redis writes executed."
  exit 0
fi
export MARS_V4_REMOTE_TUNNEL_READY=YES
bash scripts/ci/test-v4.sh "$suite"
