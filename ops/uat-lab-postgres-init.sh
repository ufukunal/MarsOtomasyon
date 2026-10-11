#!/usr/bin/env bash
# Only runs on FIRST initialization of the new, dedicated UAT PostgreSQL volume.
# Never run against shared test/production PostgreSQL.
set -Eeuo pipefail
: "${MARS_UAT_PG_APP_PASSWORD:?dedicated app role password required}"
[[ "$POSTGRES_USER" == "mars_uat_owner" ]] || { echo 'BLOCKED: wrong UAT PostgreSQL owner'; exit 77; }
[[ "$POSTGRES_DB" == postgres ]] || { echo 'BLOCKED: unexpected bootstrap database'; exit 77; }

psql --username "$POSTGRES_USER" --dbname postgres \
  --set ON_ERROR_STOP=1 --set app_password="$MARS_UAT_PG_APP_PASSWORD" <<'SQL'
CREATE ROLE mars_uat_app LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD :'app_password';
SQL

psql --username "$POSTGRES_USER" --dbname postgres --set ON_ERROR_STOP=1 <<'SQL'
CREATE DATABASE mars_uat_master OWNER mars_uat_app;
CREATE DATABASE mars_uat_demo_2026 OWNER mars_uat_app;
REVOKE ALL ON DATABASE mars_uat_master FROM PUBLIC;
REVOKE ALL ON DATABASE mars_uat_demo_2026 FROM PUBLIC;
REVOKE CONNECT ON DATABASE postgres FROM PUBLIC;
GRANT CONNECT ON DATABASE mars_uat_master TO mars_uat_app;
GRANT CONNECT ON DATABASE mars_uat_demo_2026 TO mars_uat_app;
SQL
echo 'PASS: fresh dedicated UAT DBs and unprivileged application role initialized.'
