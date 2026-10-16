#!/usr/bin/env bash
# CI only. Export a previously built and locally verified immutable Fresh UAT image.
set -Eeuo pipefail
umask 077
die(){ echo "BLOCKED: $*" >&2; exit 77; }
SHA="${1:-}"
[[ "$SHA" =~ ^[a-f0-9]{40}$ ]] || die 'full image source SHA required'
for x in sudo docker gzip sha256sum; do command -v "$x" >/dev/null || die "missing $x"; done
IMAGE="ghcr.io/ufukunal/marsotomasyon:uat-$SHA"
[[ "$(sudo docker image inspect "$IMAGE" --format '{{index .Config.Labels "org.opencontainers.image.revision"}}')" == "$SHA" ]] || die 'CI image label or tag mismatch'
sudo docker run --rm --user 33:33 --entrypoint sh "$IMAGE" -ec '
  test -r /var/www/html/artisan
  test -s /var/www/html/public/build/manifest.json
  grep -Fq "contact_transfers.override_balance" /var/www/html/app/Support/Auth/CompanyRoleProvisioner.php
' || die 'local CI image verification failed'
DIR="$HOME/mars-fresh-package"
[[ ! -L "$DIR" ]] || die 'unsafe output symlink'
mkdir -p "$DIR"; chmod 700 "$DIR"
ARCHIVE="$DIR/mars-fresh-web-$SHA.tar.gz"
[[ ! -e "$ARCHIVE" ]] || die 'refuse to overwrite image archive'
trap 'rm -f "$ARCHIVE"' ERR
sudo docker save "$IMAGE" | gzip -1 > "$ARCHIVE"
[[ -s "$ARCHIVE" ]] || die 'empty image archive'
gzip -t "$ARCHIVE" || die 'corrupt image archive'
(cd "$DIR" && sha256sum "$(basename "$ARCHIVE")" > "$(basename "$ARCHIVE").sha256")
echo "CI_OFFLINE_IMAGE=PASS"
echo "IMAGE_PACKAGE=$ARCHIVE"
echo "IMAGE_SHA=$SHA"
