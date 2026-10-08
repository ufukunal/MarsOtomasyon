#!/usr/bin/env bash
# Safe recurring health probe from mars-ci to dedicated PostgreSQL/Valkey test VM.
set -Eeuo pipefail
host=100.127.235.30
database=MarsProject_Master_Test_R3
opts=(-o BatchMode=yes -o PasswordAuthentication=no -o ConnectTimeout=7 -o ConnectionAttempts=1 -o StrictHostKeyChecking=accept-new -o LogLevel=ERROR)
echo '=== Permanent database + Valkey authenticated connectivity ==='
bash scripts/ci/remote-test-services.sh "$database"
echo '=== Remote node monitoring ==='
timeout 35 ssh "${opts[@]}" ufuk@"$host" 'bash -s' <<'REMOTE'
set -Eeuo pipefail
[[ "$(hostname)" == ufukmarsprod ]] || exit 11
[[ "$(systemctl is-active docker)" == active ]] || { echo "DOCKER_INACTIVE"; exit 12; }
[[ "$(systemctl is-enabled docker)" == enabled ]] || { echo "DOCKER_NOT_AUTOSTART"; exit 13; }
# df output is also logged so threshold decisions are auditable.
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
echo 'PERMANENT_INFRA_HEALTH_OK'
