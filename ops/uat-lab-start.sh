#!/usr/bin/env bash
# Only provisions independent UAT data containers; never runs migrations or
# starts application/web. No existing PostgreSQL/Valkey services are modified.
set -Eeuo pipefail
cd "$(dirname "$0")/.."
ROOT="$(pwd -P)"
[[ "$ROOT" == /home/*/mars-uat-* ]] || { echo 'BLOCKED: run from dedicated mars-uat-* checkout' >&2; exit 77; }
command -v openssl >/dev/null && command -v sudo >/dev/null
sudo docker info >/dev/null
sudo docker compose version >/dev/null

ENV_FILE="$HOME/.config/mars/uat-lab.env"
mkdir -p "$(dirname "$ENV_FILE")"
if [[ -e "$ENV_FILE" && ! -f "$ENV_FILE" ]] || [[ -L "$ENV_FILE" ]]; then
  echo 'BLOCKED: unexpected UAT env file type' >&2; exit 77
fi
if [[ ! -e "$ENV_FILE" ]]; then
  (umask 077; {
    printf 'MARS_UAT_SHA=%s\n' "$(git rev-parse HEAD)"
    printf 'MARS_UAT_PG_OWNER_PASSWORD=%s\n' "$(openssl rand -hex 32)"
    printf 'MARS_UAT_PG_APP_PASSWORD=%s\n' "$(openssl rand -hex 32)"
    printf 'MARS_UAT_VALKEY_PASSWORD=%s\n' "$(openssl rand -hex 32)"
    printf 'MARS_UAT_APP_KEY=base64:%s\n' "$(openssl rand -base64 32 | tr -d '\n')"
  } > "$ENV_FILE")
fi
[[ "$(stat -c '%a' "$ENV_FILE")" == 600 ]] || { echo 'BLOCKED: require UAT env 0600'; exit 77; }
EXPECTED_SHA="$(git rev-parse HEAD)"
grep -Fxq "MARS_UAT_SHA=$EXPECTED_SHA" "$ENV_FILE" || { echo 'BLOCKED: secret env was generated for another release SHA'; exit 77; }
[[ -z "$(git status --porcelain)" ]] || { echo 'BLOCKED: dirty UAT checkout'; exit 77; }

echo 'Starting ONLY new mars-uat-lab PostgreSQL/Valkey containers (no migrations).'
sudo docker compose --env-file "$ENV_FILE" -f compose.uat.lab.yml \
  up --wait -d postgres valkey
echo '=== ISOLATED UAT ROLES AND DATABASES ==='
sudo docker compose --env-file "$ENV_FILE" -f compose.uat.lab.yml \
  exec -T -u postgres postgres psql -w -X -v ON_ERROR_STOP=1 \
  -U mars_uat_owner -d postgres -P pager=off -c "
SELECT datname, pg_get_userbyid(datdba) AS owner,
       has_database_privilege('mars_uat_app', oid, 'CONNECT') AS app_connect
FROM pg_database WHERE NOT datistemplate ORDER BY datname;
SELECT rolname, rolcanlogin, rolsuper, rolcreatedb, rolcreaterole, rolbypassrls
FROM pg_roles WHERE rolname = 'mars_uat_app';
"
echo 'PASS: dedicated UAT data services started; NO migrations or web startup performed.'
