#!/usr/bin/env bash
set -euo pipefail

PG_CONTAINER="marsotomasyon-postgres-1"
ROLE_FILE="$HOME/.marsotomasyon/postgresql-roles.env"
ROTATION_DIR="$HOME/.marsotomasyon/rotations"
ROTATION_MARKER="$ROTATION_DIR/fw-imp-007-db-log-exposure-v1"

if [ -f "$ROTATION_MARKER" ]; then
  echo "ROTATE_POSTGRES_ROLES=ALREADY_APPLIED"
  exit 0
fi

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

generate_secret() {
  python3 - <<'PY'
import secrets
print(secrets.token_hex(32))
PY
}

NEW_MASTER_PASSWORD="$(generate_secret)"
NEW_APP_PASSWORD="$(generate_secret)"

docker exec -e PGPASSWORD="$MARS_PG_MASTER_PASSWORD" \
  "$PG_CONTAINER" \
  psql -h 127.0.0.1 -U "$MARS_PG_MASTER_USER" -d "$MARS_PG_DATABASE" \
  -v ON_ERROR_STOP=1 \
  -v master_role="$MARS_PG_MASTER_USER" \
  -v app_role="$MARS_PG_APP_USER" \
  -v new_master_password="$NEW_MASTER_PASSWORD" \
  -v new_app_password="$NEW_APP_PASSWORD" <<'SQL' >/dev/null
SELECT format('ALTER ROLE %I PASSWORD %L', :'master_role', :'new_master_password') \gexec
SELECT format('ALTER ROLE %I PASSWORD %L', :'app_role', :'new_app_password') \gexec
SQL

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

mkdir -p "$ROTATION_DIR"
chmod 700 "$ROTATION_DIR"
printf '%s\n' "rotated" > "$ROTATION_MARKER"
chmod 600 "$ROTATION_MARKER"

if docker exec -e PGPASSWORD="$NEW_MASTER_PASSWORD" "$PG_CONTAINER" \
  psql -h 127.0.0.1 -U "$MARS_PG_MASTER_USER" -d "$MARS_PG_DATABASE" -Atqc 'select 1' | grep -qx '1' \
  && docker exec -e PGPASSWORD="$NEW_APP_PASSWORD" "$PG_CONTAINER" \
  psql -h 127.0.0.1 -U "$MARS_PG_APP_USER" -d "$MARS_PG_DATABASE" -Atqc 'select 1' | grep -qx '1'; then
  echo "ROTATE_POSTGRES_ROLES=PASS"
else
  echo "ROTATE_POSTGRES_ROLES=VERIFY_FAIL"
  exit 1
fi
