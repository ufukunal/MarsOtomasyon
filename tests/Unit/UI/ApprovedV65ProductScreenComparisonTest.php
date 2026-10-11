<?php

use Illuminate\Support\Facades\Route;

it('keeps v65 product list labels and read-only detail destinations', function (): void {
    $prototype = file_get_contents(base_path('reference/marsotomasyon-PROTOTIP-ONAYLI-v65.html'));
    $list = file_get_contents(base_path('app/Livewire/Pages/Products/ProductList.php'));
    $stock = file_get_contents(base_path('app/Livewire/Pages/Stock/StockStatus.php'));

    expect($prototype)->toContain('product_detail_readonly')
        ->toContain('En Çok Alan Cariler')
        ->toContain('Teknik Bilgi Föyü')
        ->and($list)->toContain("'Ürün Kodu'")
        ->toContain("'Barkodu'")
        ->toContain("'Ürün Tipi'")
        ->toContain("'Satış KDV %'")
        ->toContain("route('products.show'")
        ->not->toContain("'label' => 'Detay', 'method' => 'editProduct'")
        ->and($stock)->toContain("route('products.show'")
        ->not->toContain("route('products.edit'");

    expect(Route::has('products.show'))->toBeTrue();
});

it('renders the reference tab order and only shows the identity within the overview', function (): void {
    $component = file_get_contents(base_path('app/Livewire/Pages/Products/ProductDetail.php'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/products/product-detail.blade.php'));
    $order = [
        "'overview' => 'Genel Bakış'",
        "'stock'] = 'Stok Durumu'",
        "'movements'] = 'Stok Hareketleri'",
        "'sales'] = 'Satışlar'",
        "'purchases'] = 'Alışlar'",
        "'buyers'] = 'En Çok Alan Cariler'",
        "'prices'] = 'Fiyatlar'",
        "'technical'] = 'Teknik Bilgi Föyü'",
        "'annual'] = 'Yıllık Performans'",
        "'locations'] = 'Depolar'",
        "'variants'] = 'Varyantlar'",
        "'barcodes'] = 'Barkodlar'",
        "'suppliers'] = 'Tedarikçiler'",
        "'images'] = 'Görseller'",
        "'notes'] = 'Notlar'",
        "'files'] = 'Dosyalar'",
    ];

    $previous = -1;

    foreach ($order as $marker) {
        $position = strpos($component, $marker);
        expect($position)->not->toBeFalse();
        expect($position)->toBeGreaterThan($previous);
        $previous = $position;
    }

    $tabNav = strpos($view, 'aria-label="Ürün detay sekmeleri"');
    $identity = strpos($view, 'aria-label="Ürün kimliği"');

    expect($tabNav)->not->toBeFalse()
        ->and($identity)->not->toBeFalse()
        ->and($tabNav)->toBeLessThan($identity)
        ->and($view)->toContain("@if(\$activeTab === 'overview')")
        ->toContain('product-identity')
        ->toContain('product-summary');
});

it('populates v65 analytics from posted period documents without fabricated totals', function (): void {
    $component = file_get_contents(base_path('app/Livewire/Pages/Products/ProductDetail.php'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/products/product-detail.blade.php'));

    expect($component)->toContain("where('doc.status', 'posted')")
        ->toContain("whereYear('doc.document_date', \$currentYear)")
        ->toContain('currency_quantity')
        ->toContain('topSuppliers')
        ->toContain('last_purchase_date')
        ->toContain("can('stock.view')")
        ->toContain("can('cost.view')")
        ->and($view)->toContain('{{ $buyer->last_purchase_at')
        ->toContain('Ortalama Fiyat')
        ->toContain('Kesinleşmiş Alışlar')
        ->toContain('Bağımsız teknik föy şablonu mevcut veri modelinde bulunmuyor.')
        ->not->toContain('684.000')
        ->not->toContain('10687.50');
});
