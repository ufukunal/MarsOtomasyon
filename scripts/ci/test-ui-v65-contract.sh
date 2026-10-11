#!/usr/bin/env bash
# Enforce approved K-067 v65 shared shell / component contracts.
set -Eeuo pipefail
cd "$(dirname "$0")/../.."
css=resources/css/app.css
shell=resources/views/layouts/app.blade.php
table=resources/views/livewire/components/data-table.blade.php
prototype=reference/marsotomasyon-PROTOTIP-ONAYLI-v65.html
test -s "$prototype"
test -s "$css" && test -s "$shell" && test -s "$table"
test "$(wc -c < "$css")" -lt 40000
if grep -Fq '!important' "$css"; then
  echo 'FAIL: duplicated specificity via !important in canonical UI theme' >&2
  exit 1
fi
for selector in '.sidebar-search' '.nav-group-title' '.nav-children' '.worktabs' '.topbar-breadcrumb' '.data-table-shell' '.table-toolbar' '.field-control' '.wizard-form-grid' '.document-editor-form' '.report-center' '.dashboard-grid'; do
  grep -Fq "$selector" "$css" || { echo "FAIL: v65 shared component missing $selector" >&2; exit 1; }
done
for marker in 'class="sidebar"' 'class="navigation"' 'aria-current="page"' 'class="topbar"' 'class="worktabs"' 'id="main-content"'; do
  grep -Fq "$marker" "$shell" || { echo "FAIL: v65 shell marker missing $marker" >&2; exit 1; }
done
for marker in 'wire:model.live.debounce.300ms="search"' 'wire:model.live="filterValues.' 'wire:click="exportCsv"' 'wire:click="exportXlsx"' 'class="table-footer"'; do
  grep -Fq "$marker" "$table" || { echo "FAIL: shared DataTable feature missing $marker" >&2; exit 1; }
done
# Every data grid in these critical UAT operations must be scrollable on narrower screens.
for view in \
  resources/views/livewire/production/production-order-center.blade.php \
  resources/views/livewire/production/recipe-center.blade.php \
  resources/views/livewire/production/subcontracting-center.blade.php \
  resources/views/livewire/pages/stock/transfer-detail.blade.php \
  resources/views/livewire/pages/stock/warehouse-slip-detail.blade.php \
  resources/views/livewire/channels/channel-account-center.blade.php \
  resources/views/livewire/channels/channel-listing-center.blade.php \
  resources/views/livewire/sales/document-list.blade.php; do
  grep -Fq 'class="table-scroll v65-grid-scroll"' "$view" || {
    echo "FAIL: v65 responsive table wrapper missing: $view" >&2; exit 1;
  }
done
# Prevent accidental unsafe replacement of the approved prototype.
grep -Fq 'PROTOTIP ONAYLI v65' "$prototype"
echo 'PASS: approved v65 shared UI contracts and single-theme rules'
