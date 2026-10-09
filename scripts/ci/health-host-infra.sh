#!/usr/bin/env bash
# Separate, read-only VM host metrics: may need existing authorized Tailscale SSH.
# This is intentionally independent from CI application database tests.
set -Eeuo pipefail
host=100.127.235.30
opts=(-o BatchMode=yes -o PasswordAuthentication=no -o ConnectTimeout=7 -o ConnectionAttempts=1 -o StrictHostKeyChecking=accept-new -o LogLevel=ERROR)
echo '=== VM Docker, disk and RAM host checks (existing SSH authorization required) ==='
timeout 35 ssh "${opts[@]}" ufuk@"$host" 'bash -s' <<'REMOTE'
set -Eeuo pipefail
[[ "$(hostname)" == ufukmarsprod ]] || exit 11
[[ "$(systemctl is-active docker)" == active ]] || { echo "DOCKER_INACTIVE"; exit 12; }
[[ "$(systemctl is-enabled docker)" == enabled ]] || { echo "DOCKER_NOT_AUTOSTART"; exit 13; }
df -hT /
df -h /var/lib/docker
disk_pct="$(df -P / | awk 'NR==2 {gsub(/%/,"",$5);print $5}')"
[[ "$disk_pct" =~ ^[0-9]+$ ]] || exit 14
echo "ROOT_DISK_USED_PERCENT=$disk_pct"
(( disk_pct < 85 )) || { echo "ROOT_DISK_CAPACITY_ALERT"; exit 15; }
mem_kb="$(awk '/MemAvailable:/ {print $2}' /proc/meminfo)"
[[ "$mem_kb" =~ ^[0-9]+$ ]] || exit 16
echo "MEMORY_AVAILABLE_MIB=$((mem_kb/1024))"
(( mem_kb >= 1024*1024 )) || { echo "MEMORY_PRESSURE_ALERT"; exit 17; }
for port in 55432 6379; do
  ss -H -ltn "( sport = :$port )" | grep -q LISTEN || { echo "SERVICE_PORT_MISSING:$port"; exit 18; }
done
echo "NODE_DISK_MEMORY_DOCKER_AND_PORTS_OK"
REMOTE
