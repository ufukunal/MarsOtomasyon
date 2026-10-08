#!/usr/bin/env bash
# Expand only the verified Ubuntu 24.04 TEST VM's existing root LV and ext4.
set -Eeuo pipefail
if [[ "${EUID}" -ne 0 ]] || [[ "$(hostname)" != ufukmarsprod ]] ||
   ! ip -4 addr show tailscale0 | grep -q '100.127.235.30/32'; then
  echo "Refusing LVM changes: unexpected identity or privileges" >&2; exit 2
fi
# This privileged filesystem change must use a root-owned local file.
[[ ! -L "$0" && "$(stat -c %u "$0")" == 0 ]] ||
  { echo "Install a root-owned expansion script first" >&2; exit 2; }
lv=/dev/ubuntu-vg/ubuntu-lv
[[ "$(findmnt -no SOURCE /)" == /dev/mapper/ubuntu--vg-ubuntu--lv ]] ||
  { echo "Unexpected root mount" >&2; exit 2; }
[[ "$(findmnt -no FSTYPE /)" == ext4 ]] ||
  { echo "Expected ext4 root filesystem" >&2; exit 2; }
[[ -b "$lv" ]] || { echo "Root LV not found" >&2; exit 2; }
echo "=== Before ==="
df -hT /
vgs -o vg_name,vg_size,vg_free
lvs -o lv_path,lv_size,lv_attr
free_megs="$(vgs --noheadings --units m --nosuffix -o vg_free ubuntu-vg | tr -d ' ' | cut -d . -f 1)"
if [[ ! "$free_megs" =~ ^[0-9]+$ ]]; then echo "Cannot determine free extents" >&2; exit 2; fi
if (( free_megs > 512 )); then
  echo "Expanding existing root LV using free extents: ${free_megs}MiB"
  lvextend --resizefs -l +100%FREE "$lv"
else
  echo "Root LV already expanded; no change needed"
fi
echo "=== After ==="
df -hT /
vgs -o vg_name,vg_size,vg_free
lvs -o lv_path,lv_size,lv_attr
echo 'ROOT_LVM_EXPANSION_OK'
