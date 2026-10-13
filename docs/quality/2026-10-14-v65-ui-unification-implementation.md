# MarsProject V65 — Repo-wide List / Dropdown / Action Unification

## Scope

Reference baseline `e471e78a1fc3242c08f82f6cc6b70521664c64a8`.
Original inspection covered 101 Blade files, 67 Livewire views, 13 classes using the common DataTable, and the specialized list views. No Tailwind rewrite or UI redesign has been introduced; approved V65 remains authoritative.

## Applied

### Block 0 — Shared components
- `resources/views/components/v65/list-heading.blade.php`: a single content section heading with name left and filter/action slot on the right; no second H1.
- `resources/views/components/v65/action-menu.blade.php`: a shared CSV/Excel/other action dropdown with explicit close-state and keyboard/pointer management.
- `resources/views/components/v65/simple-filter-drawer.blade.php`: compatible native right drawer for screens whose existing controls are `wire:model.live` and have no V65 staged-filter trait. Original Livewire state/behavior preserved.
- `resources/js/app.js`: exclusive dropdown controller knows `data-v65-action-menu` and existing menu selectors; Escape, outside pointer, native toggle, navigation.
- `resources/css/v65-shell.css`: consistent compact/regular button sizes, section heading position, dropdown panel, list rows, border, badge, select height/focus, responsive and print. Scope excludes approved transport-slip printing view.

### Block 1 — 13 common DataTable pages
- `resources/views/livewire/components/data-table.blade.php` now renders single header-with-filter partial for all 13 normal list variants; Cari keeps its six KPI tiles and own right-aligned heading filter. All normal DataTable exports use one dropdown. Preserve special Product print/export dialogs and photo-free Product list.
- Product and Cari detail links, stock cost permission checks, no redundant Aç/Düzenle row navigation actions are preserved in the existing Livewire classes.

### Block 2 — Dedicated lists
- Sales/Purchase document listing, transport-slip listing, Production Order/Recipe/Subcontracting lists, Imports, Channel Listing all use the heading-aligned existing V65 staged filter drawer.
- New-record actions moved to top page actions when needed, without changing `wire:click` or `@can` conditions. Sales/Purchases now group CSV/Excel under a single export menu.
- Transport slip index now orders New → Print → Export, using the existing download URL.

### Block 3 — Other lists
- Channel Sync, Print History, Quarantine Control: moved existing `wire:model.live` query filters into a consistent right-side panel; workflow controls (sync now, quarantine decision text) remain external.
- Finance account/history/securities, returns history, report-export history, period administration, operations/backup history, pricing details and settings records use common section heading/table appearance.
- Aging/supplier report controls remain report parameters, not fake list filters.
- Reports have a common grouped export menu and consistently anchored column picker.
- Channel Account new action uses the same page action slot.

### Block 4 — Embedded lists
- Contact-detail document and statement exports use a grouped dropdown, preserving method calls.
- Common scoped table styles cover Contact/Product detail, editor tables, finance/production list grids and all other `v65-list-page` tables.
- Approved shipping-slip print/label HTML is not edited. Form workflows and destructive actions are untouched.

### Block 5 — Styling
- Single V65 token family for white/blue/gray list headings, 28px top actions, 38px content controls, responsive right drawers, light-gray table heads and subtly striped/hover rows.
- Right aligned numbers use `.align-end` in shared table and several numeric-heavy specialized lists. Styles for disabled controls, focus-visible and keyboard have been added.
- Existing historical CSS rules remain where usage cannot be safely excluded via static inspection; a complete deletion of all legacy overrides was NOT performed without browser regression evidence.

### Block 6 — Quality gates
- New `tests/Unit/UI/V65UnifiedListSystemTest.php`: 6 source-contract checks for component composition, seven independent list drawers, export methods, legacy live filters, dropdown behavior and preserved Product/Cari links.
- Updated previously stale legacy source checks to recognize shared action menu/filter heading and previously renamed dashboard labels.
- Static source-level checks were done for matching components, teleport blocks, and core Blade `@if/@can/@foreach` boundaries across selected changed views.
- The PHP/Blade/Vite build, complete PHPUnit/Pest suite, responsive browser visual inspection and protected Fresh UAT publication have **not** run in this chat, so no claim of live acceptance or production readiness is made.

## Scope guard

No database migration, no demo/production data update, no finance/risk logic changes, no shipping-slip print markup updates. Controls remain permission-gated. No invented PDF/CSV backend method was introduced.

## Remaining explicit gate

Run build and UI/Livewire regression tests in isolated CI/UAT; inspect widths 390/768/1024/1440/1920; verify keyboard and overlay clipping, selected data tables, export, printing, active filters, pagination, and no menu duplication. If failed, resolve before Fresh UAT rollout. Fresh UAT is not automatically synchronized with GitHub main.
