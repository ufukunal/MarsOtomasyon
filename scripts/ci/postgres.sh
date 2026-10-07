#!/usr/bin/env bash
set -Eeuo pipefail

COMMAND="${1:-}"
PORT="${2:-}"
DATABASE="${3:-}"

if [[ -z "$COMMAND" || -z "$PORT" ]]; then
    echo "usage: postgres.sh <start|stop> <port> [database]" >&2
    exit 2
fi

PG_BINDIR="$(find /usr/lib/postgresql -maxdepth 2 -type f -name initdb -printf '%h\n' 2>/dev/null | sort -V | tail -1)"
if [[ -z "$PG_BINDIR" || ! -x "$PG_BINDIR/initdb" || ! -x "$PG_BINDIR/pg_ctl" ]]; then
    echo "PostgreSQL server binaries are not installed." >&2
    exit 1
fi

PGDATA="${RUNNER_TEMP:?RUNNER_TEMP is required}/mars-postgres-$PORT"

case "$COMMAND" in
    start)
        if [[ -z "$DATABASE" ]]; then
            echo "database name is required for start" >&2
            exit 2
        fi

        if [[ -f "$PGDATA/postmaster.pid" ]]; then
            "$PG_BINDIR/pg_ctl" -D "$PGDATA" -m immediate stop >/dev/null 2>&1 || true
        fi

        rm -rf "$PGDATA"
        "$PG_BINDIR/initdb" -D "$PGDATA" -U postgres --auth=trust --no-locale >/dev/null

        {
            echo "listen_addresses = '127.0.0.1'"
            echo "port = $PORT"
            echo "unix_socket_directories = '$RUNNER_TEMP'"
            echo "fsync = off"
            echo "synchronous_commit = off"
            echo "full_page_writes = off"
        } >> "$PGDATA/postgresql.conf"

        "$PG_BINDIR/pg_ctl" -D "$PGDATA" -l "$PGDATA/postgres.log" start >/dev/null

        READY=0
        for _ in $(seq 1 30); do
            if "$PG_BINDIR/pg_isready" -h 127.0.0.1 -p "$PORT" -U postgres >/dev/null 2>&1; then
                READY=1
                break
            fi
            sleep 1
        done

        if [[ "$READY" != "1" ]]; then
            cat "$PGDATA/postgres.log" >&2 || true
            exit 1
        fi

        "$PG_BINDIR/createdb" -h 127.0.0.1 -p "$PORT" -U postgres "$DATABASE"

        {
            echo "DB_HOST=127.0.0.1"
            echo "DB_PORT=$PORT"
            echo "DB_USERNAME=postgres"
            echo "DB_PASSWORD="
        } >> "$GITHUB_ENV"

        echo "PostgreSQL test cluster ready on port $PORT with database $DATABASE."
        ;;

    stop)
        if [[ -d "$PGDATA" ]]; then
            "$PG_BINDIR/pg_ctl" -D "$PGDATA" -m fast stop >/dev/null 2>&1 || true
            rm -rf "$PGDATA"
        fi
        ;;

    *)
        echo "unknown command: $COMMAND" >&2
        exit 2
        ;;
esac
