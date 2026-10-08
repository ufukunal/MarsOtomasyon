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
echo '=== Persistent engine configuration (read-only) ==='
[[ -r "${GITHUB_ENV:-}" ]] || { echo 'Missing protected per-job environment'; exit 21; }
pgpass="$(sed -n 's/^DB_PASSWORD=//p' "$GITHUB_ENV" | tail -n 1)"
valkeypass="$(sed -n 's/^REDIS_PASSWORD=//p' "$GITHUB_ENV" | tail -n 1)"
[[ "$pgpass" =~ ^[[:xdigit:]]{64}$ && "$valkeypass" =~ ^[[:xdigit:]]{64}$ ]] || exit 22
pg_fsync="$(PGPASSWORD="$pgpass" timeout 10 psql -w -h "$host" -p 55432 -U mars_test -d postgres -Atqc 'SHOW fsync')"
[[ "$pg_fsync" == on ]] || { echo 'POSTGRES_FSYNC_DISABLED'; exit 23; }
echo 'POSTGRES_FSYNC_ENABLED'
exec 4<>"/dev/tcp/$host/6379"
printf '*2\r\n$4\r\nAUTH\r\n$64\r\n%s\r\n' "$valkeypass" >&4
IFS= read -r -t 5 reply <&4
[[ "${reply:0:3}" == '+OK' ]] || { echo 'VALKEY_AUTH_FAILED'; exit 24; }
printf '*2\r\n$4\r\nINFO\r\n$11\r\npersistence\r\n' >&4
IFS= read -r -t 5 reply <&4
[[ "${reply:0:1}" == '$' ]] || { echo 'VALKEY_INFO_FAILED'; exit 25; }
aof_enabled=no
aof_write_ok=no
for ((i=0;i<100;i++)); do
  IFS= read -r -t 4 line <&4 || break
  line="${line%?}"
  [[ "$line" == 'aof_enabled:1' ]] && aof_enabled=yes
  [[ "$line" == 'aof_last_write_status:ok' ]] && aof_write_ok=yes
  [[ "$aof_enabled" == yes && "$aof_write_ok" == yes ]] && break
done
exec 4<&- 4>&-
unset pgpass valkeypass
[[ "$aof_enabled" == yes && "$aof_write_ok" == yes ]] || { echo "VALKEY_AOF_STATE_BAD enabled=$aof_enabled write_ok=$aof_write_ok"; exit 26; }
echo 'VALKEY_AOF_ENABLED_AND_WRITABLE'
echo 'PERMANENT_INFRA_HEALTH_OK'
