#!/usr/bin/env bash
# PROD host: checksum-verify CI exported image, load it, and promote ONLY Fresh web.
set -Eeuo pipefail
umask 077
die(){ echo "BLOCKED: $*" >&2; exit 77; }
SHA="${1:-}"
[[ "$SHA" =~ ^[a-f0-9]{40}$ ]] || die 'CI-built image SHA required'
[[ "$EUID" != 0 ]] || die 'run as normal user'
for c in sudo docker gzip sha256sum stat; do command -v "$c" >/dev/null || die "missing $c"; done
DIR="$HOME/mars-fresh-incoming"
FILE="$DIR/mars-fresh-web-$SHA.tar.gz"
SUM="$FILE.sha256"
[[ -f "$FILE" && ! -L "$FILE" && -f "$SUM" && ! -L "$SUM" ]] || die 'CI image archive or checksum missing'
[[ "$(stat -c '%U' "$FILE")" == "$(id -un)" ]] || die 'image archive not owned by deployment user'
(cd "$DIR" && sha256sum -c --status "$(basename "$SUM")") || die 'CI image checksum mismatch'
gzip -t "$FILE" || die 'CI image archive corrupt'
IMAGE="ghcr.io/ufukunal/marsotomasyon:uat-$SHA"
echo '=== Import CI-built image into Fresh host (no build, no GHCR auth) ==='
sudo docker load -i "$FILE" || die 'Docker image import failed'
[[ "$(sudo docker image inspect "$IMAGE" --format '{{index .Config.Labels "org.opencontainers.image.revision"}}')" == "$SHA" ]] || die 'imported image revision mismatch'
ROOT="$(cd -- "$(dirname -- "$0")/.." && pwd -P)"
PROMOTE="$ROOT/ops/uat-fresh-ci-image-promote.sh"
[[ -f "$PROMOTE" && ! -L "$PROMOTE" ]] || die 'Fresh promotion script missing'
MARS_FRESH_OFFLINE_IMAGE=1 bash "$PROMOTE" "$SHA" || die 'Fresh web promotion failed; inspect rollback output'
echo 'FRESH_23_OFFLINE=PASS'
