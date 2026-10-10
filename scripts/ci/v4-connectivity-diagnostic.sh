#!/usr/bin/env bash
# Diagnostic only: never installs PostgreSQL/Valkey, creates DBs or writes PROD data.
set -Eeuo pipefail
[[ "${RUNNER_NAME:-}" == "mars-ubuntu-04" ]] || { echo "BLOCKED: unexpected runner"; exit 77; }
host=192.168.239.132
ssh_port=10000
echo "RUNNER_ID=${RUNNER_NAME}"
echo "RUNNER_HOSTNAME=$(hostname)"
echo "RUNNER_OS=$(uname -s)"
echo "RUNNER_USER=$(id -un)"
echo "CLIENT_PHP=$(command -v php >/dev/null && php -r 'echo PHP_VERSION;' || echo MISSING)"
for client in ssh sshpass nc psql redis-cli docker; do
  if command -v "$client" >/dev/null 2>&1; then echo "CLIENT_${client}=PRESENT"; else echo "CLIENT_${client}=MISSING"; fi
done
for extension in pdo_pgsql redis bcmath; do
  if php -r "exit(extension_loaded('$extension') ? 0 : 1);" 2>/dev/null; then
    echo "PHP_CLIENT_EXTENSION_${extension}=PRESENT"
  else
    echo "PHP_CLIENT_EXTENSION_${extension}=MISSING"
  fi
done
if command -v ip >/dev/null 2>&1; then
  ip -4 route get "$host" 2>&1 | sed -E 's/src [0-9.]+/src [REDACTED]/g' || true
fi
tcp_probe() {
  local name="$1" port="$2"
  if command -v nc >/dev/null 2>&1; then
    if timeout 5 nc -z -w 3 "$host" "$port" >/dev/null 2>&1; then
      echo "TCP_${name}=OPEN"
      return 0
    fi
  elif timeout 5 bash -c ":</dev/tcp/$host/$port" >/dev/null 2>&1; then
    echo "TCP_${name}=OPEN"
    return 0
  fi
  echo "TCP_${name}=CLOSED_OR_FILTERED"
  return 1
}
tcp_probe SSH_10000 "$ssh_port" || { echo "RESULT=BLOCKED_SSH_NETWORK"; exit 77; }
tcp_probe POSTGRES_55432 55432 || true
tcp_probe VALKEY_6379 6379 || true
if [[ -n "${MARS_V4_SSH_PRIVATE_KEY:-}" ]]; then
  echo "SSH_AUTH_MATERIAL=KEY"
elif [[ -n "${MARS_V4_SSH_PASSWORD:-}" ]]; then
  echo "SSH_AUTH_MATERIAL=PASSWORD_SECRET"
else
  echo "SSH_AUTH_MATERIAL=MISSING"
  echo "RESULT=NETWORK_ONLY_AUTH_UNAVAILABLE"
  exit 0
fi
tmp="$(mktemp -d)"; chmod 700 "$tmp"
trap 'rm -rf "$tmp"' EXIT
opts=(-p "$ssh_port" -o StrictHostKeyChecking=yes -o ConnectTimeout=7
  -o ConnectionAttempts=1 -o LogLevel=ERROR)
if [[ -n "${MARS_V4_SSH_KNOWN_HOSTS:-}" ]]; then
  printf '%s\n' "$MARS_V4_SSH_KNOWN_HOSTS" > "$tmp/known_hosts"
  chmod 600 "$tmp/known_hosts"
  opts+=(-o UserKnownHostsFile="$tmp/known_hosts")
else
  echo "SSH_HOST_KEY=SYSTEM_KNOWN_HOSTS_ONLY"
fi
cmd=(ssh)
if [[ -n "${MARS_V4_SSH_PRIVATE_KEY:-}" ]]; then
  printf '%s\n' "$MARS_V4_SSH_PRIVATE_KEY" > "$tmp/id_key"; chmod 600 "$tmp/id_key"
  opts+=(-o BatchMode=yes -o IdentitiesOnly=yes -i "$tmp/id_key")
else
  if ! command -v sshpass >/dev/null 2>&1; then
    echo "RESULT=SSH_PASSWORD_CLIENT_MISSING"; exit 0
  fi
  cmd=(sshpass -e ssh)
  export SSHPASS="$MARS_V4_SSH_PASSWORD"
  opts+=(-o BatchMode=no -o PreferredAuthentications=password -o PubkeyAuthentication=no)
fi
# Remote commands are read-only (hostname, port listeners, Docker metadata).
if ! timeout 23 "${cmd[@]}" "${opts[@]}" "ufuk@$host" 'bash -s' <<'REMOTE'
set -Eeuo pipefail
echo "REMOTE_HOSTNAME=$(hostname)"
echo "REMOTE_SSH_PORT=$(ss -H -ltn "( sport = :10000 )" | grep -q LISTEN && echo LISTEN || echo NOT_LISTEN)"
for spec in 55432:POSTGRES 6379:VALKEY; do
  port="${spec%%:*}"; name="${spec##*:}"
  if ss -H -ltn "( sport = :$port )" | grep -q LISTEN; then
    echo "REMOTE_${name}_PORT=LISTEN"
  else
    echo "REMOTE_${name}_PORT=NOT_LISTEN"
  fi
done
if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
  echo "REMOTE_DOCKER=RUNNING_ACCESSIBLE"
  docker ps --format '{{.Names}}|{{.Image}}|{{.Status}}|{{.Ports}}' |
    grep -Ei 'postgres|valkey|redis' || true
else
  echo "REMOTE_DOCKER=NOT_ACCESSIBLE_AS_USER"
fi
REMOTE
then
  echo "RESULT=REMOTE_SSH_AUTH_FAILED"
  exit 77
fi
echo "RESULT=REMOTE_READ_ONLY_DIAGNOSTIC_FINISHED"
