#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
OUTPUT="$ROOT/MarsProject_UAT_250Urun_100Cari_Kapsamli_Demo_Set_2026.zip"
EXPECT="9e3b63ccf9346e96cb21cc3a1438330684d514939c78adf8bf830029efc1be8a"
case "${1:-}" in
  ""|--extract) ;;
  *) echo 'Kullanim: bash demo/reconstruct.sh [--extract]' >&2; exit 2 ;;
esac
TMP="$(mktemp "$ROOT/.mars-demo-zip-XXXXXX")"
trap 'rm -f "$TMP"' EXIT
for i in $(seq -w 1 9); do
  printf -v num "%03d" "$i"
  PART="$ROOT/archive/part-$num.bin"
  [[ -f "$PART" ]] || { echo "EKSIK: $PART" >&2; exit 1; }
  cat "$PART" >> "$TMP"
done
printf '%s  %s\n' "$EXPECT" "$TMP" | sha256sum -c - >/dev/null
mv -f "$TMP" "$OUTPUT"
echo "DOGRU SHA256: $OUTPUT"
if [[ "${1:-}" == "--extract" ]]; then
  command -v python3 >/dev/null || { echo "python3 eksik" >&2; exit 1; }
  python3 - "$OUTPUT" "$ROOT" <<'PY'
from pathlib import Path
from zipfile import ZipFile
import sys
archive, root = Path(sys.argv[1]), Path(sys.argv[2]).resolve()
with ZipFile(archive) as z:
    for item in z.infolist():
        dest=(root/item.filename).resolve()
        if not dest.is_relative_to(root):
            raise SystemExit("Unsafe archive path")
    z.extractall(root)
print("MEDYA VE TEKNIK FÖYLER CIKARTILDI")
PY
fi
