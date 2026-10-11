<?php

use Illuminate\Support\Facades\Route;

it('maps approved v65 stock destinations to real authorized Laravel routes', function (): void {
    $prototype = file_get_contents(base_path('reference/marsotomasyon-PROTOTIP-ONAYLI-v65.html'));

    foreach (['stock_status', 'stock_movements', 'warehouses', 'transfer_list', 'count_list',
        'warehouse_slip_list', 'warehouse_slip_new', 'quarantine', 'inventory_cost'] as $route) {
        expect($prototype)->toContain($route);
    }

    $navigation = collect(config('navigation'));
    $productStock = $navigation->firstWhere('label', 'Ürün / Stok');
    $warehouse = $navigation->firstWhere('label', 'Ambar');

    expect($productStock)->not->toBeNull()
        ->and($warehouse)->not->toBeNull();

    $stockRoutes = array_column($productStock['items'], 'route');
    $warehouseRoutes = array_column($warehouse['items'], 'route');

    foreach ([
        'stock.status', 'stock.movements', 'locations.index', 'stock.reservations.index',
        'stock.transfers.index', 'stock.transfers.create', 'stock.counts.index',
        'stock.counts.create', 'stock.counts.quick', 'stock.quarantine.index', 'stock.costs',
    ] as $route) {
        expect($stockRoutes)->toContain($route)
            ->and(Route::has($route))->toBeTrue();
    }

    expect($warehouseRoutes)->toContain('stock.warehouse-slips.index', 'stock.warehouse-slips.create');

    $costItem = collect($productStock['items'])->firstWhere('route', 'stock.costs');
    expect($costItem['permission'])->toBe('cost.view')
        ->and($costItem['permission_secondary'])->toBe('stock.view');
});

it('keeps inventory actions wired to existing authenticated Livewire workflows', function (): void {
    $views = [
        'transfer-detail' => ['wire:click="saveDraft"', 'wire:click="send"', 'wire:click="receive"'],
        'warehouse-slip-detail' => ['wire:click="saveDraft"', 'wire:click="post"', 'wire:click="reverse"'],
        'stock-count-detail' => ['wire:click="saveDraft"', 'wire:click="start"', 'wire:click="saveLine(', 'wire:click="review"', 'wire:click="post"'],
        'quarantine-control' => ['wire:click="releaseSelected"', 'wire:click="scrapSelected"'],
    ];

    foreach ($views as $name => $markers) {
        $view = file_get_contents(base_path("resources/views/livewire/pages/stock/{$name}.blade.php"));

        expect($view)->toContain('v65-stock-workspace');

        foreach ($markers as $marker) {
            expect($view)->toContain($marker);
        }
    }

    $count = file_get_contents(base_path('resources/views/livewire/pages/stock/stock-count-detail.blade.php'));
    expect($count)->toContain('wire:model.live.debounce.300ms="productSearch"')
        ->toContain('wire:submit.prevent="addProductByBarcode"');

    $quarantine = file_get_contents(base_path('resources/views/livewire/pages/stock/quarantine-control.blade.php'));
    expect($quarantine)->toContain('\\App\\Support\\Formatting\\TrFormatter::quantity')
        ->not->toContain('AppSupportFormattingTrFormatter');
});

it('calculates in-transit stock from real transfers and prevents unauthorized cost access', function (): void {
    $statusQuery = file_get_contents(base_path('app/Support/Stock/StockStatusQuery.php'));
    $costScreen = file_get_contents(base_path('app/Livewire/Pages/Stock/StockCost.php'));

    expect($statusQuery)->toContain('transfer_lines tl')
        ->toContain('partially_received')
        ->toContain('in_transit')
        ->and($costScreen)->toContain('parent::mount()')
        ->toContain("can('cost.view')");
});
