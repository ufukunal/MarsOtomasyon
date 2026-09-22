#!/usr/bin/env bash
set -euo pipefail

PG_CONTAINER="marsotomasyon-postgres-1"
ROLE_FILE="$HOME/.marsotomasyon/postgresql-roles.env"
ROTATION_DIR="$HOME/.marsotomasyon/rotations"
ROTATION_MARKER="$ROTATION_DIR/fw-imp-007-db-log-exposure-v1"

test -f "$ROLE_FILE"
test "$(stat -c '%a' "$ROLE_FILE")" = "600"

# Never enable xtrace in this script.
# shellcheck disable=SC1090
source "$ROLE_FILE"

: "${MARS_PG_DATABASE:?}"
: "${MARS_PG_MASTER_USER:?}"
: "${MARS_PG_MASTER_PASSWORD:?}"
: "${MARS_PG_APP_USER:?}"
: "${MARS_PG_APP_PASSWORD:?}"

mapfile -t PG_NETWORKS < <(
  docker inspect --format '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}' "$PG_CONTAINER" |
    sed '/^[[:space:]]*$/d'
)
test "${#PG_NETWORKS[@]}" -eq 1
TEST_NETWORK="${PG_NETWORKS[0]}"

network_login() {
  local user="$1"
  local password="$2"

  docker run --rm     --network "$TEST_NETWORK"     -e PGPASSWORD="$password"     postgres:18-bookworm     psql -h "$PG_CONTAINER" -U "$user" -d "$MARS_PG_DATABASE" -Atqc 'select 1'     2>/dev/null | grep -qx '1'
}

apply_passwords() {
  local master_password="$1"
  local app_password="$2"

  docker exec -e PGPASSWORD="$MARS_PG_MASTER_PASSWORD"     "$PG_CONTAINER"     psql -h 127.0.0.1 -U "$MARS_PG_MASTER_USER" -d "$MARS_PG_DATABASE"     -v ON_ERROR_STOP=1     -v master_role="$MARS_PG_MASTER_USER"     -v app_role="$MARS_PG_APP_USER"     -v new_master_password="$master_password"     -v new_app_password="$app_password" <<'SQL' >/dev/null
SELECT format('ALTER ROLE %I PASSWORD %L', :'master_role', :'new_master_password') \gexec
SELECT format('ALTER ROLE %I PASSWORD %L', :'app_role', :'new_app_password') \gexec
SQL
}

if [ -f "$ROTATION_MARKER" ]; then
  if network_login "$MARS_PG_MASTER_USER" "$MARS_PG_MASTER_PASSWORD"     && network_login "$MARS_PG_APP_USER" "$MARS_PG_APP_PASSWORD"; then
    echo "ROTATE_POSTGRES_ROLES=ALREADY_APPLIED"
    exit 0
  fi

  echo "ROTATE_POSTGRES_ROLES=REPAIR_EXISTING"
  apply_passwords "$MARS_PG_MASTER_PASSWORD" "$MARS_PG_APP_PASSWORD"

  if network_login "$MARS_PG_MASTER_USER" "$MARS_PG_MASTER_PASSWORD"     && network_login "$MARS_PG_APP_USER" "$MARS_PG_APP_PASSWORD"; then
    echo "ROTATE_POSTGRES_ROLES=REPAIR_PASS"
    exit 0
  fi

  echo "ROTATE_POSTGRES_ROLES=REPAIR_FAIL"

  echo "ROTATE_POSTGRES_AUTH_DIAGNOSTIC_BEGIN"
  docker exec -e PGPASSWORD="$MARS_PG_MASTER_PASSWORD" "$PG_CONTAINER" \
    psql -h 127.0.0.1 -U "$MARS_PG_MASTER_USER" -d "$MARS_PG_DATABASE" \
    -v ON_ERROR_STOP=0 \
    -v master_role="$MARS_PG_MASTER_USER" \
    -v app_role="$MARS_PG_APP_USER" \
    -AtF'|' <<'SQL' || true
SHOW password_encryption;
SELECT rolname,
       rolcanlogin::text,
       COALESCE(rolvaliduntil::text, 'infinity'),
       CASE
         WHEN rolpassword IS NULL THEN 'none'
         WHEN rolpassword LIKE 'SCRAM-SHA-256$%' THEN 'scram-sha-256'
         WHEN rolpassword LIKE 'md5%' THEN 'md5'
         ELSE 'other'
       END
FROM pg_authid
WHERE rolname IN (:'master_role', :'app_role')
ORDER BY rolname;
SELECT line_number,
       type,
       array_to_string(database, ','),
       array_to_string(user_name, ','),
       COALESCE(address, ''),
       auth_method,
       COALESCE(error, '')
FROM pg_hba_file_rules
WHERE error IS NOT NULL
   OR user_name IS NULL
   OR :'master_role' = ANY(user_name)
   OR :'app_role' = ANY(user_name)
   OR 'all' = ANY(user_name)
ORDER BY line_number;
SQL
  echo "ROTATE_POSTGRES_AUTH_DIAGNOSTIC_END"
  exit 1
fi

generate_secret() {
  python3 - <<'PY'
import secrets
print(secrets.token_hex(32))
PY
}

NEW_MASTER_PASSWORD="$(generate_secret)"
NEW_APP_PASSWORD="$(generate_secret)"

apply_passwords "$NEW_MASTER_PASSWORD" "$NEW_APP_PASSWORD"

TMP_FILE="$(mktemp "$HOME/.marsotomasyon/postgresql-roles.env.XXXXXX")"
trap 'rm -f "$TMP_FILE"' EXIT
chmod 600 "$TMP_FILE"
cat > "$TMP_FILE" <<EOF
MARS_PG_DATABASE=$MARS_PG_DATABASE
MARS_PG_MASTER_USER=$MARS_PG_MASTER_USER
MARS_PG_MASTER_PASSWORD=$NEW_MASTER_PASSWORD
MARS_PG_APP_USER=$MARS_PG_APP_USER
MARS_PG_APP_PASSWORD=$NEW_APP_PASSWORD
EOF
mv "$TMP_FILE" "$ROLE_FILE"
trap - EXIT

MARS_PG_MASTER_PASSWORD="$NEW_MASTER_PASSWORD"
MARS_PG_APP_PASSWORD="$NEW_APP_PASSWORD"

if network_login "$MARS_PG_MASTER_USER" "$MARS_PG_MASTER_PASSWORD"   && network_login "$MARS_PG_APP_USER" "$MARS_PG_APP_PASSWORD"; then
  mkdir -p "$ROTATION_DIR"
  chmod 700 "$ROTATION_DIR"
  printf '%s\n' "rotated-and-network-verified" > "$ROTATION_MARKER"
  chmod 600 "$ROTATION_MARKER"
  echo "ROTATE_POSTGRES_ROLES=PASS"
else
  echo "ROTATE_POSTGRES_ROLES=VERIFY_FAIL"
  exit 1
fi
