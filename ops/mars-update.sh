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
shopt -s nullglob
candidates=("$HOME"/mars-uat-*)
repo=''
for d in "${candidates[@]}"; do
  if [[ -d "$d/.git" && -f "$d/ops/uat-lab-resume.sh" ]]; then
    if [[ -n "$repo" ]]; then echo 'HATA: Birden cok UAT checkout var; otomatik secim guvenli degil' >&2; exit 77; fi
    repo="$d"
  fi
done
[[ -n "$repo" ]] || { echo 'HATA: /home/<kullanici>/mars-uat-* checkout bulunamadi' >&2; exit 77; }
cd "$repo"
[[ -z "$(git status --porcelain)" ]] || { echo 'HATA: Yerel degisiklik var, guncelleme durduruldu' >&2; exit 77; }
[[ "$(git branch --show-current)" == main ]] || { echo 'HATA: main dalinda degilsiniz' >&2; exit 77; }
echo '=== GitHub main son surumu kontrol ediliyor ==='
git fetch --prune origin main
target="$(git rev-parse refs/remotes/origin/main)"
current="$(git rev-parse HEAD)"
[[ "$target" =~ ^[0-9a-f]{40}$ ]] || exit 77
git merge-base --is-ancestor "$current" "$target" || { echo 'HATA: main ileri-sarilamiyor; kaynak kod degistirilmedi' >&2; exit 77; }
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
git merge --ff-only refs/remotes/origin/main
[[ "$(git rev-parse HEAD)" == "$target" ]] || exit 77
echo '=== Izole UAT Lab kontrolleri ve guncelleme ==='
bash ops/uat-lab-resume.sh
echo "BASARILI: UAT yayin commit $target"
