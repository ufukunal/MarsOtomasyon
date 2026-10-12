#!/usr/bin/env bash
# MarsProject UAT updater. Invoke as: mars update
# Never runs as root; uses the existing isolated UAT Lab and its guarded resume.
set -Eeuo pipefail
if [[ "${1:-}" != update || $# -ne 1 ]]; then echo 'Kullanim: mars update' >&2; exit 2; fi
if (( EUID == 0 )); then echo 'HATA: Root olarak calistirmayin. Normal UAT kullanicisi ile girin.' >&2; exit 77; fi
command -v git >/dev/null || { echo 'HATA: git eksik' >&2; exit 77; }
command -v sudo >/dev/null || { echo 'HATA: sudo eksik' >&2; exit 77; }
command -v docker >/dev/null || { echo 'HATA: docker eksik' >&2; exit 77; }
ENV_FILE="$HOME/.config/mars/uat-lab.env"
[[ -f "$ENV_FILE" && ! -L "$ENV_FILE" ]] || { echo "HATA: Mevcut UAT env yok: $ENV_FILE" >&2; exit 77; }
[[ "$(stat -c '%a' "$ENV_FILE")" == 600 && "$(stat -c '%U' "$ENV_FILE")" == "$(id -un)" ]] || { echo 'HATA: UAT env izin/sahiplik hatasi' >&2; exit 77; }
repo="${MARS_UAT_REPO:-$HOME/mars-uat-129189}"
[[ -d "$repo/.git" && -f "$repo/ops/uat-lab-resume.sh" && -f "$repo/compose.uat.lab.yml" ]] || {
  echo "HATA: UAT Lab checkout bulunamadi: $repo" >&2; exit 77;
}
cd "$repo"
[[ -z "$(git status --porcelain)" ]] || { echo 'HATA: Yerel degisiklik var, guncelleme durduruldu' >&2; exit 77; }
# Detached HEAD is a supported release state; no implicit overwrite of local history.
echo '=== GitHub main son surumu kontrol ediliyor ==='
git fetch --prune origin main
# The newest main commit may be docs/ops-only and have no published image.
# Select the latest main commit that changes a UAT-image build input.
target="$(git log -1 --format=%H refs/remotes/origin/main -- Dockerfile.uat vite.config.js compose.uat.yml compose.uat.lab.yml app resources routes lang config database/seeders scripts/ci/verify-vite-manifest.php ops/mars-update.sh ops/uat-lab-resume.sh ops/uat-lab-start.sh ops/uat-lab-postgres-init.sh .github/workflows/uat-image-publish.yml)"
current="$(git rev-parse HEAD)"
[[ "$target" =~ ^[0-9a-f]{40}$ ]] || exit 77
git merge-base --is-ancestor "$current" refs/remotes/origin/main || {
  echo 'HATA: Yerel commit main dalinda degil, guncelleme durduruldu' >&2; exit 77;
}
image="ghcr.io/ufukunal/marsotomasyon:uat-$target"
echo "Hedef commit: $target"
echo "Hedef image: $image"
echo '=== Onayli commit icin UAT image bekleniyor (en fazla 10 dakika) ==='
ready=0
for i in {1..20}; do
  if sudo docker pull "$image"; then ready=1; break; fi
  echo "Image hazir degil ($i/20); 30 saniye sonra tekrar..."
  sleep 30
done
(( ready == 1 )) || { echo 'HATA: UAT image bulunamadi. Yayin yapilmadi.' >&2; exit 77; }
revision="$(sudo docker image inspect "$image" --format '{{index .Config.Labels "org.opencontainers.image.revision"}}')"
[[ "$revision" == "$target" ]] || { echo 'HATA: Image revision hedef commit ile eslesmiyor' >&2; exit 77; }
# Resume is image-SHA-pinned; use the exact published commit in a detached checkout.
# Never reset branches or switch databases.
git switch --detach "$target"
[[ "$(git rev-parse HEAD)" == "$target" ]] || exit 77
echo '=== Izole UAT Lab kontrolleri ve guncelleme ==='
bash ops/uat-lab-resume.sh
running="$(sudo docker inspect mars-uat-lab-web-1 --format '{{index .Config.Labels "org.opencontainers.image.revision"}}')"
[[ "$running" == "$target" ]] || { echo 'HATA: Calisan konteyner revizyonu eslesmiyor' >&2; exit 77; }
echo "BASARILI: UAT yayin commit $target"
