#!/usr/bin/env bash
# Run ONLY on the CI host from an isolated clean Git checkout.
# Requires CI Docker login with package:write authorization to GHCR.
set -Eeuo pipefail
umask 077
fail(){ echo "BLOCKED: $*" >&2; exit 77; }
for c in git docker grep; do command -v "$c" >/dev/null || fail "missing $c"; done
ROOT="$(git rev-parse --show-toplevel)" || fail 'Git checkout missing'
cd "$ROOT"
[[ -z "$(git status --porcelain --untracked-files=no)" ]] || fail 'tracked changes in CI checkout'
SHA="$(git rev-parse HEAD)"
[[ "$SHA" =~ ^[a-f0-9]{40}$ ]] || fail 'invalid HEAD SHA'
[[ "${1:-$SHA}" == "$SHA" ]] || fail 'expected SHA mismatch'
[[ -f Dockerfile.uat && -f app/Support/Auth/CompanyRoleProvisioner.php ]] || fail 'source missing'
grep -Fq "'contact_transfers.override_balance'" app/Support/Auth/CompanyRoleProvisioner.php || fail 'role matrix incomplete'
IMAGE="ghcr.io/ufukunal/marsotomasyon:uat-$SHA"
echo "=== CI BUILD: $SHA ==="
docker build -f Dockerfile.uat --label "org.opencontainers.image.revision=$SHA" -t "$IMAGE" .
echo '=== CI IMAGE PREFLIGHT ==='
[[ "$(docker image inspect "$IMAGE" --format '{{index .Config.Labels "org.opencontainers.image.revision"}}')" == "$SHA" ]] || fail 'image revision mismatch'
docker run --rm --user 33:33 --entrypoint sh "$IMAGE" -ec '
  test -r /var/www/html/artisan
  test -s /var/www/html/public/build/manifest.json
  test -r /var/www/html/app/Support/Auth/CompanyRoleProvisioner.php
  grep -Fq "contact_transfers.override_balance" /var/www/html/app/Support/Auth/CompanyRoleProvisioner.php
  php -l /var/www/html/app/Support/Auth/CompanyRoleProvisioner.php
  php /var/www/html/artisan view:cache >/dev/null
  for f in /var/www/html/storage/framework/views/*.php; do
    [ -f "$f" ] || continue
    php -l "$f" >/dev/null || exit 1
  done
' || fail 'CI image preflight failed; no push'
echo '=== CI PUSH TO GHCR ==='
docker push "$IMAGE" || fail 'GHCR push failed'
echo "CI_IMAGE_PUSH=PASS"
echo "TARGET_SHA=$SHA"
echo "IMAGE=$IMAGE"
