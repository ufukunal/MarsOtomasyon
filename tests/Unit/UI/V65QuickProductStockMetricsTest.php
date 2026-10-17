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
        ->toContain('grid-template-columns:minmax(240px,2.35fr) repeat(4,minmax(110px,1fr))')
        ->toContain('width:min(980px,calc(100vw - 48px))')
        ->toContain('.v65-reference-suggestion-metrics{display:contents}')
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

it('keeps the expanded quick product search readable on mobile and tablets', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($css)->toContain('flex:0 1 720px')
        ->toContain('@media(max-width:1050px)')
        ->toContain('@media(max-width:720px)')
        ->toContain('@media(max-width:420px)')
        ->toContain('grid-column:1/-1');
});
