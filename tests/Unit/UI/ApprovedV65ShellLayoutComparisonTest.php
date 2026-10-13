<?php

use Illuminate\Support\Facades\Route;

it('aligns shared navigation and page actions to the approved v65 screen shell', function (): void {
    $navigation = config('navigation');
    $labels = array_column($navigation, 'label');

    expect($labels)->toContain('Cariler', 'Satış', 'Alış', 'Kasa / Banka', 'Çek / Senet');
    expect(array_search('Ürün / Stok', $labels, true))
        ->toBeLessThan(array_search('Satış', $labels, true));
    expect(array_search('Ambar', $labels, true))
        ->toBeLessThan(array_search('Alış', $labels, true));

    $productRoutes = array_column(collect($navigation)->firstWhere('label', 'Ürün / Stok')['items'], 'route');
    expect($productRoutes)->toContain('products.index', 'stock.status', 'stock.movements');
    expect(array_search('products.index', $productRoutes, true))
        ->toBeLessThan(array_search('stock.status', $productRoutes, true));

    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));

    expect($layout)->toContain('class="sidebar"')
        ->toContain('class="nav-child-dot"')
        ->toContain('v65-page-actions')
        ->toContain('Alım İşlemleri')
        ->toContain('Satış İşlemleri')
        ->toContain('window.print()')
        ->toContain("route('logout')");
});

it('preserves true stock totals and working export actions on the v65 product grid', function (): void {
    $productList = file_get_contents(base_path('app/Livewire/Pages/Products/ProductList.php'));
    $table = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));

    expect($productList)->toContain('on_hand_v65')
        ->toContain('reserved_v65')
        ->toContain('available_v65')
        ->toContain('SUM(reserved + consignment_reserved)')
        ->toContain("can('stock.view')")
        ->toContain("route('products.show'")
        ->and($table)->toContain('v65-products-hint')
        ->not->toContain('v65-products-heading')
        ->toContain('v65-shared-table-title')
        ->toContain('wire:click="exportXlsx"')
        ->toContain('wire:click="exportCsv"');
});

it('uses posted transaction records and due drafts instead of sample dashboard data', function (): void {
    $dashboard = file_get_contents(base_path('app/Livewire/Reporting/Dashboard.php'));
    $view = file_get_contents(base_path('resources/views/livewire/reporting/dashboard.blade.php'));

    expect($dashboard)->toContain('DocumentType::SalesInvoice')
        ->toContain('DocumentType::Collection')
        ->toContain("where('currency', 'TRY')")
        ->toContain('GREATEST(net_balance, 0)')
        ->toContain('cash_accounts as account')
        ->toContain("where('status', 'posted')")
        ->toContain("where('status', 'draft')")
        ->toContain("whereDate('due_date', '<=', \$today)")
        ->and($view)->toContain('Bugün Yapılacaklar')
        ->toContain('Hızlı Durum')
        ->toContain('Bugünkü Satış')
        ->toContain('Bugünkü Tahsilat')
        ->toContain('Carilerden Alacaklarımız')
        ->toContain('Kasa + Banka')
        ->not->toContain('184.250');
});

it('keeps existing real routes for the new shell actions', function (): void {
    foreach ([
        'products.index', 'products.show', 'products.edit', 'finance.contact-aging',
        'purchases.orders.edit', 'sales.orders.edit', 'returns.center',
    ] as $name) {
        expect(Route::has($name))->toBeTrue($name);
    }
});
