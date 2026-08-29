#!/usr/bin/env bash
set -euo pipefail

fail() {
    echo "ERROR: $*" >&2
    exit 1
}

command -v php >/dev/null || fail "php is missing"
command -v composer >/dev/null || fail "composer is missing"
command -v python3 >/dev/null || fail "python3 is missing"
command -v psql >/dev/null || fail "psql is missing"
command -v createdb >/dev/null || fail "createdb is missing"
command -v dropdb >/dev/null || fail "dropdb is missing"

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
[[ "$PHP_VERSION" == "8.5" ]] || fail "PHP 8.5 is required; found ${PHP_VERSION}"

for extension in pdo_pgsql redis sockets intl zip; do
    php -m | grep -Fxqi "$extension" || fail "PHP extension missing: $extension"
done

CPU_COUNT="$(nproc)"
MEM_KB="$(awk '/MemTotal:/ {print $2}' /proc/meminfo)"
MEM_GB="$((MEM_KB / 1024 / 1024))"

echo "CPU: ${CPU_COUNT}"
echo "RAM: ${MEM_GB} GiB"
echo "PHP: $(php -r 'echo PHP_VERSION;')"
echo "Composer: $(composer --version --no-ansi 2>/dev/null | head -n1)"

if (( CPU_COUNT < 6 )); then
    echo "WARNING: fewer than 6 logical CPUs; 3-way sharding may not be optimal" >&2
fi

if (( MEM_GB < 10 )); then
    echo "WARNING: less than 10 GiB RAM; 3 concurrent runners may pressure memory" >&2
fi

DB_USER="${DB_USERNAME:-marsci}"
DB_HOST_VALUE="${DB_HOST:-/var/run/postgresql}"

psql -h "$DB_HOST_VALUE" -U "$DB_USER" -d postgres -Atqc 'select 1' | grep -Fxq 1 \
    || fail "PostgreSQL connection failed for ${DB_USER}@${DB_HOST_VALUE}"

CAN_CREATE_DB="$(psql -h "$DB_HOST_VALUE" -U "$DB_USER" -d postgres -Atqc "select rolcreatedb from pg_roles where rolname = current_user")"
[[ "$CAN_CREATE_DB" == "t" ]] || fail "PostgreSQL role ${DB_USER} needs CREATEDB"

if command -v valkey-cli >/dev/null; then
    valkey-cli -h "${REDIS_HOST:-127.0.0.1}" -p "${REDIS_PORT:-6379}" ping | grep -Fxq PONG \
        || fail "Valkey is not responding"
elif command -v redis-cli >/dev/null; then
    redis-cli -h "${REDIS_HOST:-127.0.0.1}" -p "${REDIS_PORT:-6379}" ping | grep -Fxq PONG \
        || fail "Redis/Valkey is not responding"
else
    fail "valkey-cli or redis-cli is required"
fi

echo "Self-hosted PostgreSQL runner prerequisites: OK"
