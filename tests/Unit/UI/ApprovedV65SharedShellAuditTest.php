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
        ->not->toContain('!important');
});

it('uses the same reference table scaffolding for product, stock and other lists', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));

    expect($view)->toContain('v65-products-hint')
        ->toContain('v65-products-heading')
        ->toContain('v65-shared-list-heading')
        ->toContain('v65-shared-table-title')
        ->toContain('$navigationItem')
        ->toContain('wire:model.live.debounce.300ms="search"')
        ->toContain('wire:click="exportCsv"')
        ->toContain('wire:click="exportXlsx"')
        ->toContain("route(\$emptyAction['route'])");
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
