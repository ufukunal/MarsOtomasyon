<?php

use Illuminate\Support\Facades\Route;

it('removes price list menu entries and disables standalone price list screens', function (): void {
    $navigation = file_get_contents(base_path('config/navigation.php'));
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $routes = file_get_contents(base_path('routes/web.php'));

    expect($navigation)->not->toContain("'route' => 'price-lists.index'")
        ->and($layout)->not->toContain("'price-lists.index'")
        ->and($routes)->not->toContain("->name('price-lists.index')")
        ->not->toContain("->name('price-lists.detail')")
        ->and(Route::has('price-lists.index'))->toBeFalse()
        ->and(Route::has('price-lists.detail'))->toBeFalse();
});

it('removes the price list choice from customer card, product detail and import wizard', function (): void {
    $contact = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-form.blade.php'));
    $detail = file_get_contents(base_path('resources/views/livewire/pages/products/product-detail.blade.php'));
    $import = file_get_contents(base_path('resources/views/livewire/pages/import/import-wizard.blade.php'));
    $wizard = file_get_contents(base_path('app/Livewire/Pages/Import/ImportWizard.php'));

    expect($contact)->not->toContain('wire:model="priceListId"')
        ->and($detail)->not->toContain('Fiyat Listesi</th>')
        ->toContain('Satış Fiyatı')
        ->and($import)->not->toContain("'price_list'=>'Fiyat Listesi'")
        ->and($wizard)->toContain("'in:contact,product,opening_stock'")
        ->not->toContain('in:contact,product,price_list,opening_stock');
});

it('retains prices and historical price-list assignments without destructive migrations', function (): void {
    $contactEditor = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactForm.php'));
    $salesEditor = file_get_contents(base_path('app/Livewire/Sales/BaseSalesDocumentEditor.php'));
    $productDetail = file_get_contents(base_path('resources/views/livewire/pages/products/product-detail.blade.php'));

    // Hidden assignment must survive when the customer is edited.
    expect($contactEditor)->toContain("'price_list_id' => \$data['priceListId']")
        ->and($salesEditor)->toContain('PriceResolver')
        ->and($productDetail)->toContain('$product->list_price');
});
