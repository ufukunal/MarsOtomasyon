<?php

use Illuminate\Support\Facades\Route;

it('opens stock row links in read-only details rather than edit forms', function (): void {
    foreach ([
        'products.show', 'stock.movements.show', 'stock.reservations.show',
        'locations.show', 'stock.transfers.show', 'stock.counts.show',
        'stock.warehouse-slips.show',
    ] as $route) {
        expect(Route::has($route))->toBeTrue($route);
    }

    $stockStatus = file_get_contents(base_path('app/Livewire/Pages/Stock/StockStatus.php'));
    $stockMovements = file_get_contents(base_path('app/Livewire/Pages/Stock/StockMovements.php'));
    $productList = file_get_contents(base_path('app/Livewire/Pages/Products/ProductList.php'));
    $reservationList = file_get_contents(base_path('app/Livewire/Pages/Stock/ReservationList.php'));
    $locationList = file_get_contents(base_path('app/Livewire/Pages/Catalog/LocationList.php'));
    $stockCounts = file_get_contents(base_path('app/Livewire/Pages/Stock/StockCountResults.php'));

    expect($stockStatus)->toContain("route('products.show'")
        ->not->toContain("route('products.edit'")
        ->and($stockMovements)->toContain("route('products.show'")
        ->toContain("route('stock.movements.show'")
        ->and($productList)->toContain("'label' => 'Detay'")
        ->toContain("route('products.show'")
        ->and($reservationList)->toContain("route('products.show'")
        ->toContain("route('stock.reservations.show'")
        ->and($locationList)->toContain("route('locations.show'")
        ->and($stockCounts)->toContain("route('products.show'");
});

it('retains separate editing permissions and read-only stock detail controls', function (): void {
    foreach ([
        'app/Livewire/Pages/Stock/StockMovementDetail.php' => "can('stock.view')",
        'app/Livewire/Pages/Stock/ReservationDetail.php' => "can('reservations.view')",
        'app/Livewire/Pages/Catalog/LocationDetail.php' => "can('locations.view')",
    ] as $path => $required) {
        $screen = file_get_contents(base_path($path));
        expect($screen)->toContain($required);
    }

    $status = file_get_contents(base_path('app/Livewire/Pages/Stock/StockStatus.php'));
    expect($status)->toContain("can('products.view')");

    $movement = file_get_contents(base_path('resources/views/livewire/pages/stock/stock-movement-detail.blade.php'));
    expect($movement)->toContain('@if($canViewCost)')
        ->toContain('Salt okunur');
    $reservation = file_get_contents(base_path('resources/views/livewire/pages/stock/reservation-detail.blade.php'));
    expect($reservation)->toContain('salt okunurdur');
});
