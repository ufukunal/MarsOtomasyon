<?php

it('left-aligns quick product results and displays price physical reserved and available quantities', function (): void {
    $blade = file_get_contents(base_path('resources/views/components/v65/document-prototype-toolbar.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($blade)->toContain('v65-reference-suggestion-identity')
        ->toContain('v65-reference-suggestion-metrics')
        ->toContain('Liste Fiyatı')
        ->toContain('Stok')
        ->toContain('Rezerve')
        ->toContain('Kullanılabilir')
        ->toContain('TrFormatter::quantity')
        ->and($css)->toContain('.v65-reference-suggestions>button')
        ->toContain('flex-direction:column;align-items:stretch')
        ->toContain('text-align:left!important')
        ->toContain('v65-reference-suggestion-available');
});

it('uses batched period stock balances and hides all stock figures without permission', function (): void {
    $trait = file_get_contents(base_path('app/Livewire/Concerns/WithPrototypeDocumentControls.php'));
    $blade = file_get_contents(base_path('resources/views/components/v65/document-prototype-toolbar.blade.php'));

    expect($trait)->toContain("can('stock.view')")
        ->toContain("whereIn('product_id', \$products->modelKeys())")
        ->toContain('SUM(quantity)')
        ->toContain('SUM(reserved + consignment_reserved)')
        ->toContain('SUM(quantity - reserved - consignment_reserved - quarantine)')
        ->toContain("lookup_available', \$product->setAvailability()")
        ->toContain('PriceResolver::class')
        ->and($blade)->toContain("@if(auth()->user()?->can('stock.view'))")
        ->toContain('Stok görüntüleme yetkisi yok')
        ->toContain('data-v65-reference-quick-choice')
        ->toContain('insertQuickProduct(');
});
