#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "Run as root (sudo)." >&2
    exit 1
fi

if [[ "${I_UNDERSTAND_THIS_IS_A_DISPOSABLE_CI_HOST:-}" != "1" ]]; then
    cat >&2 <<'EOF'
Refusing to continue.
This script tunes PostgreSQL for disposable CI speed (including fsync=off).
It must NEVER be used on production or any server holding valuable data.
Re-run with:
  I_UNDERSTAND_THIS_IS_A_DISPOSABLE_CI_HOST=1
EOF
    exit 2
fi

if [[ ! -r /etc/os-release ]]; then
    echo "Ubuntu /etc/os-release not found" >&2
    exit 3
fi

. /etc/os-release
if [[ "${ID:-}" != "ubuntu" ]]; then
    echo "This bootstrap targets Ubuntu; found ID=${ID:-unknown}" >&2
    exit 3
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y ca-certificates curl gnupg git unzip python3 software-properties-common

# PHP 8.5
add-apt-repository -y ppa:ondrej/php

# PostgreSQL 18 from PGDG.
install -d -m 0755 /usr/share/postgresql-common/pgdg
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    | gpg --dearmor -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.gpg
printf 'deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.gpg] https://apt.postgresql.org/pub/repos/apt %s-pgdg main\n' "$VERSION_CODENAME" \
    > /etc/apt/sources.list.d/pgdg.list

apt-get update
apt-get install -y \
    php8.5-cli php8.5-curl php8.5-gd php8.5-intl php8.5-mbstring php8.5-pgsql \
    php8.5-redis php8.5-sockets php8.5-xml php8.5-zip \
    postgresql-18 postgresql-client-18

if apt-cache show valkey-server >/dev/null 2>&1; then
    apt-get install -y valkey-server
    systemctl enable --now valkey-server
else
    apt-get install -y redis-server
    systemctl enable --now redis-server
fi

# Composer v2 with installer signature verification.
EXPECTED_SIGNATURE="$(curl -fsSL https://composer.github.io/installer.sig)"
curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
if [[ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]]; then
    rm -f /tmp/composer-setup.php
    echo "Composer installer signature mismatch" >&2
    exit 4
fi
php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
rm -f /tmp/composer-setup.php

# Dedicated unprivileged OS account for all three GitHub runner processes.
if ! id marsci >/dev/null 2>&1; then
    useradd --create-home --shell /bin/bash marsci
fi
install -d -o marsci -g marsci /home/marsci/.cache/composer
for runner in 1 2 3; do
    install -d -o marsci -g marsci "/opt/actions-runner-${runner}"
done

systemctl enable --now postgresql

# Peer-authenticated local PostgreSQL role. No CI database password is needed.
runuser -u postgres -- psql -v ON_ERROR_STOP=1 -d postgres <<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'marsci') THEN
        CREATE ROLE marsci LOGIN CREATEDB;
    ELSE
        ALTER ROLE marsci WITH LOGIN CREATEDB;
    END IF;
END
$$;
SQL

# Disposable CI-only PostgreSQL tuning. Durability is intentionally disabled;
# every job creates an isolated throw-away database.
runuser -u postgres -- psql -v ON_ERROR_STOP=1 -d postgres <<'SQL'
ALTER SYSTEM SET shared_buffers = '2GB';
ALTER SYSTEM SET effective_cache_size = '6GB';
ALTER SYSTEM SET maintenance_work_mem = '512MB';
ALTER SYSTEM SET work_mem = '16MB';
ALTER SYSTEM SET max_wal_size = '4GB';
ALTER SYSTEM SET checkpoint_timeout = '30min';
ALTER SYSTEM SET checkpoint_completion_target = '0.9';
ALTER SYSTEM SET fsync = 'off';
ALTER SYSTEM SET synchronous_commit = 'off';
ALTER SYSTEM SET full_page_writes = 'off';
ALTER SYSTEM SET jit = 'off';
SQL

systemctl restart postgresql

# Verify local peer auth and CREATEDB before runner registration.
runuser -u marsci -- psql -h /var/run/postgresql -U marsci -d postgres -Atqc 'select 1' | grep -Fxq 1
TEST_DB="mars_ci_bootstrap_$$"
runuser -u marsci -- createdb -h /var/run/postgresql -U marsci "$TEST_DB"
runuser -u marsci -- dropdb -h /var/run/postgresql -U marsci "$TEST_DB"

if command -v valkey-cli >/dev/null; then
    valkey-cli ping | grep -Fxq PONG
else
    redis-cli ping | grep -Fxq PONG
fi

cat <<'EOF'

Host bootstrap complete.

Next:
1. GitHub -> Settings -> Actions -> Runners -> New self-hosted runner.
2. Download/extract the runner package into each directory:
     /opt/actions-runner-1
     /opt/actions-runner-2
     /opt/actions-runner-3
3. Configure three UNIQUE runner names, all with the custom label: mars-ci
4. Install/start each runner as the OS user: marsci
5. Run the workflow: PostgreSQL Fast Self-Hosted

Do not paste the GitHub runner registration token into chat or commit it to the repo.
EOF
