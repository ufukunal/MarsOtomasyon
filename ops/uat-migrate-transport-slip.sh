#!/usr/bin/env bash
set -Eeuo pipefail
ENV="$HOME/.config/mars/uat-lab.env"
test -f "$ENV" && test ! -L "$ENV"
test "$(stat -c '%a' "$ENV")" = 600
PGID="$(sudo docker ps -q --filter 'name=^/mars-uat-lab-postgres-1$')"
WEBID="$(sudo docker ps -q --filter 'name=^/mars-uat-lab-web-1$')"
test -n "$PGID" && test -n "$WEBID"
for CID in "$PGID" "$WEBID"; do
  test "$(sudo docker inspect "$CID" --format '{{index .Config.Labels "com.docker.compose.project"}}')" = mars-uat-lab
done
test "$(sudo docker inspect "$PGID" --format '{{index .Config.Labels "com.docker.compose.service"}}')" = postgres
test "$(sudo docker inspect "$WEBID" --format '{{index .Config.Labels "com.docker.compose.service"}}')" = web
test "$(sudo docker inspect "$PGID" --format '{{range .Mounts}}{{if eq .Destination "/var/lib/postgresql/data"}}{{.Name}}{{end}}{{end}}')" = mars-uat-lab_mars_uat_lab_pg
DIR="$(sudo docker inspect "$WEBID" --format '{{index .Config.Labels "com.docker.compose.project.working_dir"}}')"
test -f "$DIR/compose.uat.lab.yml"
SAFE="$(sudo docker exec -u postgres "$PGID" psql -X -w -U mars_uat_owner -d postgres -Atqc "SELECT CASE WHEN (SELECT COUNT(*) FROM pg_database WHERE datname IN ('mars_uat_master','mars_uat_demo_2026'))=2 AND NOT has_database_privilege('mars_uat_app','postgres','CONNECT') THEN 'SAFE' ELSE 'BLOCKED' END;")"
test "$SAFE" = SAFE || { echo 'BLOCKED: not isolated UAT' >&2; exit 77; }
OWNER_PASSWORD="$(sed -n 's/^MARS_UAT_PG_OWNER_PASSWORD=//p' "$ENV")"
test -n "$OWNER_PASSWORD"
export DB_PASSWORD="$OWNER_PASSWORD"
unset OWNER_PASSWORD
sudo docker compose --env-file "$ENV" -f "$DIR/compose.uat.lab.yml" --profile application run --rm -T --no-deps -e DB_USERNAME=mars_uat_owner -e DB_PASSWORD -e DB_PERIOD_DATABASE=mars_uat_demo_2026 --entrypoint php web artisan migrate --database=period --path=database/migrations/period/0001_12_01_000010_create_transport_slips_tables.php --force
unset DB_PASSWORD
sudo docker exec -u postgres "$PGID" psql -X -w -v ON_ERROR_STOP=1 -U mars_uat_owner -d mars_uat_demo_2026 -c 'GRANT SELECT, INSERT, UPDATE, DELETE ON transport_slips, transport_slip_items TO mars_uat_app; GRANT USAGE, SELECT ON SEQUENCE transport_slips_id_seq, transport_slip_items_id_seq TO mars_uat_app;'
echo 'PASS: isolated NOYA transport slip migration'
