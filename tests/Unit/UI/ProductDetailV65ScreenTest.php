<?php

use Illuminate\Support\Facades\Route;

it('exposes a distinct authorized v65 product detail page', function (): void {
    expect(Route::has('products.index'))->toBeTrue()
        ->and(Route::has('products.show'))->toBeTrue()
        ->and(Route::has('products.edit'))->toBeTrue();

    $screen = file_get_contents(base_path('resources/views/livewire/pages/products/product-detail.blade.php'));
    $component = file_get_contents(base_path('app/Livewire/Pages/Products/ProductDetail.php'));
    $list = file_get_contents(base_path('app/Livewire/Pages/Products/ProductList.php'));

    expect($screen)->toContain('product-identity')
        ->toContain('Stok Hareketleri')
        ->toContain('Ürünü Düzenle')
        ->toContain("route('products.images.show'")
        ->and($component)->toContain("authorize('view',")
        ->toContain("can('cost.view')")
        ->toContain("where('product_id',")
        ->toContain('selectTab')
        ->toContain('Genel Bakış')
        ->and($list)->toContain("route('products.show'")
        ->toContain("'label' => 'Detay'");
});

it('keeps editable product configuration options backed by product data', function (): void {
    $form = file_get_contents(base_path('app/Livewire/Pages/Products/ProductForm.php'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/products/product-form.blade.php'));
    expect($form)->toContain("'products' =>")
        ->and($view)->toContain("route('products.show'")
        ->toContain('wire:submit="save"');
});

it('omits purchase and sales action menus and creation shortcuts from product details', function (): void {
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/products/product-detail.blade.php'));
    $detailHeader = substr($layout, strpos($layout, "if (\$routeName === 'products.show')"), 1300);

    expect($detailHeader)->not->toContain('Alım İşlemleri')->not->toContain('Satış İşlemleri')
        ->and($view)->not->toContain('Teklif Oluştur')->not->toContain('Sipariş Oluştur')
        ->not->toContain('v65-quick-invoice')
        ->toContain('Stok Hareketleri')->toContain('Stok Sayımları')->toContain('Ürünü Düzenle');
});

it('shows collection-based technical PDFs with an authorized product-scoped route', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/pages/products/product-detail.blade.php'));
    $handler = file_get_contents(base_path('app/Http/Controllers/ProductTechnicalSheetController.php'));

    expect(Route::has('products.technical-sheet.show'))->toBeTrue()
        ->and($view)->toContain("\$row->collection === 'Teknik Föy'")
        ->toContain("route('products.technical-sheet.show'")
        ->and($handler)->toContain("authorize('view', \$product)")
        ->toContain("where('collection', 'Teknik Föy')")
        ->toContain("where('mime', 'application/pdf')")
        ->toContain("whereKey(\$attachment->id)");
});
