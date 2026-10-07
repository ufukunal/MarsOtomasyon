#!/usr/bin/env bash
set -Eeuo pipefail

COMMAND="${1:-}"
PORT="${2:-0}"
DATABASE="${3:-}"

PG_VERSION="16.15.0"
CACHE_BASE="${HOME:?HOME is required}/.cache/mars-postgres"
CACHE_DIR="$CACHE_BASE/$PG_VERSION-$(uname -m)"

portable_metadata() {
    case "$(uname -m)" in
        x86_64)
            echo "embedded-postgres-binaries-linux-amd64|postgres-linux-x86_64.txz"
            ;;
        aarch64|arm64)
            echo "embedded-postgres-binaries-linux-arm64v8|postgres-linux-arm_64.txz"
            ;;
        *)
            echo "Unsupported runner architecture: $(uname -m)" >&2
            return 1
            ;;
    esac
}

ensure_portable() {
    if [[ -x "$CACHE_DIR/bin/initdb" && -x "$CACHE_DIR/bin/pg_ctl" ]]; then
        return
    fi

    mkdir -p "$CACHE_BASE"

    META="$(portable_metadata)"
    ARTIFACT="${META%%|*}"
    ARCHIVE="${META#*|}"
    JAR="$CACHE_BASE/$ARTIFACT-$PG_VERSION.jar"
    URL="https://repo1.maven.org/maven2/io/zonky/test/postgres/$ARTIFACT/$PG_VERSION/$ARTIFACT-$PG_VERSION.jar"

    LOCK="$CACHE_DIR.lock"
    if mkdir "$LOCK" 2>/dev/null; then
        trap 'rm -rf "$LOCK"' RETURN
        rm -rf "$CACHE_DIR"
        mkdir -p "$CACHE_DIR"

        if [[ ! -s "$JAR" ]]; then
            curl --fail --location --retry 3 --silent --show-error "$URL" -o "$JAR"
        fi

        unzip -p "$JAR" "$ARCHIVE" | tar -xJ -C "$CACHE_DIR"
        chmod -R u+rwX "$CACHE_DIR"

        [[ -x "$CACHE_DIR/bin/initdb" ]] || {
            echo "Portable PostgreSQL bundle did not contain bin/initdb." >&2
            exit 1
        }
    else
        for _ in $(seq 1 120); do
            if [[ -x "$CACHE_DIR/bin/initdb" ]]; then
                return
            fi
            sleep 1
        done

        echo "Timed out waiting for portable PostgreSQL cache." >&2
        exit 1
    fi
}

resolve_bindir() {
    SYSTEM_BINDIR="$(find /usr/lib/postgresql -maxdepth 2 -type f -name initdb -printf '%h\n' 2>/dev/null | sort -V | tail -1)"
    if [[ -n "$SYSTEM_BINDIR" && -x "$SYSTEM_BINDIR/initdb" && -x "$SYSTEM_BINDIR/pg_ctl" ]]; then
        echo "$SYSTEM_BINDIR"
        return
    fi

    ensure_portable
    echo "$CACHE_DIR/bin"
}

PG_BINDIR="$(resolve_bindir)"
PGDATA="${RUNNER_TEMP:?RUNNER_TEMP is required}/mars-postgres-$PORT"

case "$COMMAND" in
    ensure)
        "$PG_BINDIR/initdb" --version
        "$PG_BINDIR/postgres" --version
        ;;

    start)
        if [[ -z "$DATABASE" || "$PORT" == "0" ]]; then
            echo "usage: postgres.sh start <port> <database>" >&2
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
        if [[ "$PORT" == "0" ]]; then
            echo "usage: postgres.sh stop <port>" >&2
            exit 2
        fi

        if [[ -d "$PGDATA" ]]; then
            "$PG_BINDIR/pg_ctl" -D "$PGDATA" -m fast stop >/dev/null 2>&1 || true
            rm -rf "$PGDATA"
        fi
        ;;

    *)
        echo "usage: postgres.sh <ensure|start|stop> [port] [database]" >&2
        exit 2
        ;;
esac
