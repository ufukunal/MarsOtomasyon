<?php

use Illuminate\Support\Facades\Route;

it('applies one v65 shell asset to all authenticated modules', function (): void {
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($layout)->toContain('resources/css/v65-shell.css')
        ->toContain('MarsOtomasyon')
        ->toContain('class="sidebar"')
        ->toContain('class="topbar"')
        ->toContain('class="worktabs"')
        ->toContain('class="page-header"')
        ->toContain('class="page-actions v65-page-actions"')
        ->toContain('$routeFamily')
        ->toContain('str_starts_with($item')
        ->not->toContain('<style>')
        ->and($css)->toContain('.app-shell .sidebar')
        ->toContain('.app-shell .navigation')
        ->toContain('.app-shell .topbar')
        ->toContain('.app-shell .workspace-tab')
        ->toContain('.app-shell .page-header')
        ->toContain('.app-shell .data-table')
        ->toContain('.document-editor-form')
        ->toContain('.app-shell .v65-product-detail')
        ->toContain('.app-shell .v65-home-workbench')
        ->toContain('Approved prototype v65 effective button rules');
});

it('keeps v65 daily dashboard visible and older period widgets accessible without mock data', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/reporting/dashboard.blade.php'));
    $component = file_get_contents(base_path('app/Livewire/Reporting/Dashboard.php'));
    expect($view)->toContain('Bugün Yapılacaklar')
        ->toContain('Hızlı Durum')
        ->toContain('<details class="v65-home-extended">')
        ->toContain('Diğer Dönem Göstergeleri')
        ->and($component)->toContain('Kimden Alacağım Var?')
        ->toContain("where('status', 'posted')");
});

it('uses the same reference table scaffolding for product, stock and other lists', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));

    expect($view)->toContain('v65-products-hint')
        ->toContain('v65-shared-table-title')
        ->not->toContain('v65-products-heading')
        ->not->toContain('v65-shared-list-heading')
        ->toContain('v65-products-advanced')
        ->toContain('$navigationItem')
        ->toContain('wire:model.live.debounce.300ms="search"')
        ->toContain('wire:click="exportCsv"')
        ->toContain('wire:click="exportXlsx"')
        ->toContain("route(\$emptyAction['route'])")
        ->toContain('class="table-toolbar-actions"')
        ->toContain('wire:change="setPerPage($event.target.value)"')
        ->toContain('<strong>Sonuçlar</strong>');
});

it('keeps UI shell separated from existing application functionality', function (): void {
    $paths = [
        'resources/views/livewire/pages/products/product-detail.blade.php',
        'resources/views/livewire/reporting/dashboard.blade.php',
        'resources/views/livewire/sales/document-editor.blade.php',
        'resources/views/livewire/purchases/document-editor.blade.php',
        'resources/views/livewire/finance/collection-form.blade.php',
        'resources/views/livewire/production/production-order-center.blade.php',
    ];

    foreach ($paths as $path) {
        expect(is_file(base_path($path)))->toBeTrue($path);
    }

    expect(Route::has('products.show'))->toBeTrue();
    expect(Route::has('stock.status'))->toBeTrue();
    expect(Route::has('sales.invoices.index'))->toBeTrue();
    expect(Route::has('purchases.invoices.index'))->toBeTrue();
});

it('standardizes primary UI control sizing, tab overflow and product quick actions', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));
    $warehouseCss = file_get_contents(base_path('resources/css/v65-warehouse.css'));
    $product = file_get_contents(base_path('resources/views/livewire/pages/products/product-detail.blade.php'));
    $warehouse = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-list.blade.php'));
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));

    expect($css)->toContain('min-height:var(--ctl)')
        ->toContain('scrollbar-width:none')
        ->toContain('.v65-quick-actions')
        ->not->toContain('min-height:29px')
        ->and($warehouseCss)->toContain('height:var(--ctl)')
        ->and($product)->toContain('v65-quick-actions')
        ->toContain('v65-quick-action')
        ->and($warehouse)->not->toContain('<div class="wsl-head">')
        ->toContain('class="empty-state"')
        ->toContain("route('warehouse.transport.create')")
        ->and($layout)->toContain("\$routeName === 'warehouse.transport.index'")
        ->toContain('+ Yeni Ambar Fişi');
});

it('keeps shared table selection accessible and empty rows aligned after hiding columns', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));

    expect($view)->toContain('$visibleColumnCount = collect($columns)->filter(')
        ->toContain('colspan="{{ $visibleColumnCount + 1 + (count($rowActions) > 0 ? 1 : 0) }}"')
        ->toContain('aria-label="Kayıt {{ $row->getKey() }} seç"')
        ->toContain('scope="col"')
        ->toContain('wire:click="toggleColumn(')
        ->toContain('wire:click="runRowAction(');
});

it('keeps catalog empty actions permission-scoped and report downloads visibly actionable', function (): void {
    $category = file_get_contents(base_path('resources/views/livewire/pages/catalog/category-list.blade.php'));
    $export = file_get_contents(base_path('resources/views/livewire/reporting/export-center.blade.php'));
    $location = file_get_contents(base_path('resources/views/livewire/pages/catalog/location-detail.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($category)->toContain('@can(\'product_categories.create\')')
        ->toContain('class="empty-state v65-category-empty"')
        ->toContain('route(\'categories.create\')')
        ->and($export)->toContain('class="button-secondary" href="{{ route(\'reports.exports.download\', $job) }}"')
        ->and($location)->not->toContain('<strong>{{ $location->code }} · {{ $location->name }}</strong>')
        ->and($css)->toContain('.app-shell a.button-secondary{display:inline-flex')
        ->toContain('.app-shell .empty-state .button-link')
        ->toContain('.app-shell .category-tree .empty-state');
});

it('preserves actual warehouse table-cell layout and readable field typography', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-warehouse.css'));

    expect($css)->toContain('.app-shell .wsl-actions{white-space:nowrap;vertical-align:middle}')
        ->not->toContain('.app-shell .wsl-actions{display:flex')
        ->toContain('font-size:11px;vertical-align:middle')
        ->toContain('text-align:right;font-size:11px;font-weight:700')
        ->toContain('.app-shell .wsl-info{background:#eef8fc')
        ->toContain('.app-shell .wsl-warn{background:#fff6df');
});

it('aligns warehouse action sets without changing live actions or permissions', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-warehouse.css'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-editor.blade.php'));

    expect($css)->toContain('--wsl-action-height:var(--ctl)')
        ->toContain('.wsl-footer-pair>.wsl-footer-btn{flex:1 1 auto}')
        ->toContain('.wsl-footer-group-print .wsl-footer-btn')
        ->toContain('.wsl-footer-group-document .wsl-footer-btn')
        ->and($view)->toContain("wire:click=\"openPreview('slip')\"")
        ->toContain("wire:click=\"openPreview('labels')\"")
        ->toContain('wire:click="advance"')
        ->toContain('wire:click="cancel"')
        ->toContain('type="submit" class="wsl-footer-btn wsl-footer-btn-primary"');
});

it('keeps shared module button sets consistent without restyling specialized controls', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($css)->toContain('/* Shared v65 button sets across every authenticated module.')
        ->toContain('--v65-btn-height:38px')
        ->toContain('.app-shell .row-actions{white-space:normal;vertical-align:middle}')
        ->toContain('.app-shell :where(.button-danger){background:#dc2626')
        ->toContain('.app-shell :where(.button-secondary,a.button-secondary){background:#fff')
        ->toContain('.page-actions>button')
        ->not->toContain('.app-shell :where(.button-link,button:not(.button-secondary)');
});

it('uses one print and PDF control per shared toolbar while retaining the same print target', function (): void {
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($layout)->toContain('Yazdır / PDF')
        ->not->toContain('>Yazdır</button>')
        ->not->toContain('>PDF</button>')
        ->not->toContain('>PDF</a>')
        ->and($css)->toContain('/* v65 toolbar alignment pass: shared data tables, filters and bulk operations. */')
        ->toContain('.app-shell .table-toolbar-actions .column-picker>summary')
        ->toContain('.app-shell .bulk-bar button');
});

it('follows the final approved v65 action-button cascade instead of the former guessed theme', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));
    $guest = file_get_contents(base_path('resources/css/app.css'));
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));

    expect($css)->toContain('--v65-btn-height:38px')
        ->toContain('--v65-btn-primary:#1a73e8')
        ->toContain('--v65-btn-danger:#dc2626')
        ->toContain('min-height:28px!important;height:28px!important')
        ->toContain('font-size:11.5px!important;font-weight:600!important')
        ->toContain('border-radius:6px!important;box-shadow:none!important')
        ->toContain('.app-shell .wsl-footer{--wsl-action-height:38px}')
        ->and($guest)->toContain('v65 approved guest/setup action controls')
        ->and($layout)->toContain('page-action-menu page-action-more')
        ->toContain('<summary>Diğer');
});
