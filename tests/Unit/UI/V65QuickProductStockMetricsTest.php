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
        ->toContain('grid-template-columns:minmax(190px,.88fr) minmax(0,1.6fr)')
        ->toContain('width:100%;max-width:100%')
        ->toContain('grid-template-columns:minmax(0,1.24fr) repeat(3,minmax(0,1fr))')
        ->toContain('column-gap:12px')
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

    expect($css)->toContain('flex:0 1 790px')
        ->toContain('@media(max-width:940px)')
        ->toContain('@media(max-width:580px)')
        ->toContain('grid-template-columns:repeat(2,minmax(0,1fr))')
        ->toContain('scrollbar-gutter:stable');
});

it('keeps the dropdown exactly the same width as the product search textbox', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    // The search field uses width:100% inside the same positioned container.
    expect($css)->toContain('.v65-reference-quick-product>input[type=search]')
        ->toContain('.v65-reference-products-tools>.v65-reference-quick-product')
        ->toContain('.v65-reference-suggestions{')
        ->toContain('left:0;right:auto')
        ->toContain('width:100%;max-width:100%')
        ->toContain('box-sizing:border-box')
        ->not->toContain('width:min(1180px,calc(100vw - 32px))');
});

it('shows Ctrl/Meta multi-select without breaking the single-click product action', function (): void {
    $toolbar = file_get_contents(base_path('resources/views/components/v65/document-prototype-toolbar.blade.php'));
    expect($toolbar)->toContain("selectedIds: []")
        ->toContain('$event.ctrlKey || $event.metaKey')
        ->toContain('is-multi-selected')
        ->toContain('$wire.insertQuickProduct(')
        ->toContain('$wire.insertQuickProducts([...selectedIds])')
        ->toContain('Seçilenleri Ekle')
        ->toContain('Seçimi Temizle')
        ->toContain('x-on:input="selectedIds = []"');
});

it('prevents unsourced or duplicate product IDs in a multi-insert request', function (): void {
    $trait = file_get_contents(base_path('app/Livewire/Concerns/WithPrototypeDocumentControls.php'));
    expect($trait)->toContain('public function insertQuickProducts(array $productIds): void')
        ->toContain("count($productIds) <= 12")
        ->toContain("in_array($normalized, $ids, true)")
        ->toContain("array_diff($ids, $visible) === []")
        ->toContain('$this->quickProductOptions()->modelKeys()')
        ->toContain('$this->insertQuickProduct($id)')
        ->toContain("->status === 'draft'");
});

it('unclips the product dropdown and raises its parent stacking context', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));
    expect($css)->toContain('.v65-reference-products-card:focus-within')
        ->toContain('position:relative;overflow:visible;z-index:2')
        ->toContain('.v65-reference-products-tools .v65-reference-suggestions')
        ->toContain('z-index:120')
        ->toContain('.v65-reference-multi-footer')
        ->toContain('position:sticky;bottom:0');
});
