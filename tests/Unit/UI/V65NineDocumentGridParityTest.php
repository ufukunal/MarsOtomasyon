<?php

it('uses one 10-column product header and row action modal across sale and purchase editors', function (): void {
    $head = file_get_contents(base_path('resources/views/components/v65/document-product-table-head.blade.php'));
    $action = file_get_contents(base_path('resources/views/components/v65/document-line-actions.blade.php'));
    expect(substr_count($head, '<th scope="col"'))->toBe(10)
        ->and($head)->toContain('{{ $priceLabel }}')
        ->and($action)->toContain('data-v65-modal-open')
        ->toContain('wire:click="removeLine(');

    foreach (['sales', 'purchases'] as $side) {
        $view = file_get_contents(base_path("resources/views/livewire/{$side}/document-editor.blade.php"));
        expect($view)->toContain('<x-v65.document-product-table-head')
            ->toContain('<x-v65.document-line-actions')
            ->toContain('data-v65-fast-product-search')
            ->toContain('data-v65-add-product-focus')
            ->toContain('v65-reference-inline-summary')
            ->toContain('wire:model.blur="lines.{{ $index }}.quantity"');
        expect($view)->not->toContain('v65-reference-entry-row');
    }
});

it('retains goods receipt source-only restrictions and the shared totals rules', function (): void {
    $purchase = file_get_contents(base_path('resources/views/livewire/purchases/document-editor.blade.php'));
    $summary = file_get_contents(base_path('resources/views/components/v65/document-summary.blade.php'));
    $controls = file_get_contents(base_path('app/Livewire/Concerns/WithFastDocumentLines.php'));
    expect($purchase)->toContain('$isGoodsReceipt ? collect()')
        ->toContain('Kaynak Satır')
        ->toContain('Mal Kabul Kalemleri')
        ->and($summary)->toContain('v65-prototype-total-strip')
        ->toContain('wire:model.blur="additionalDiscountAmount"')
        ->and($controls)->toContain('ensureNextEntryRow');
});

it('toggles quick product search without creating fictitious product records', function (): void {
    $toolbar = file_get_contents(base_path('resources/views/components/v65/document-prototype-toolbar.blade.php'));
    $js = file_get_contents(base_path('resources/js/app.js'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));
    expect($toolbar)->toContain('fastEntry = !fastEntry')
        ->toContain('x-show="fastEntry"')
        ->toContain('insertQuickProduct(')
        ->and($js)->toContain('data-v65-add-product-focus')
        ->toContain("form?.querySelector('[data-v65-reference-quick-input]')")
        ->toContain('data-v65-reference-quick-choice')
        ->and($css)->toContain('v65-reference-line-menu-button')
        ->toContain('v65-reference-totals-layout');
});

it('hides the unused trailing product row and keeps the primary search functional', function (): void {
    foreach (['sales', 'purchases'] as $side) {
        $view = file_get_contents(base_path("resources/views/livewire/{$side}/document-editor.blade.php"));
        expect($view)->toContain('@if(! $isEntryRow)')
            ->toContain('Yeni ürün eklemek için üstteki ürün arama alanını kullanın.')
            ->not->toContain('v65-reference-entry-row')
            ->not->toContain('Yeni boş satır ürün ara');
    }
    $js = file_get_contents(base_path('resources/js/app.js'));
    expect($js)->toContain("form?.querySelector('[data-v65-reference-quick-input]')")
        ->not->toContain("form?.querySelector('.v65-reference-entry-row");
});

it('keeps quote search always visible without the quick-entry toggle', function (): void {
    $toolbar = file_get_contents(base_path('resources/views/components/v65/document-prototype-toolbar.blade.php'));
    $sales = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($toolbar)->toContain("@if(\$editable && \$screenType !== 'quote')")
        ->toContain("@if(\$screenType !== 'quote') x-show=\"fastEntry\" @endif")
        ->toContain('wire:model.live.debounce.250ms="quickProductSearch"')
        ->toContain("insertQuickProducts([...selectedIds])")
        ->and($sales)->toContain("aria-label=\"Ürün ve hizmetler\" @if(\$screenType !== 'quote')")
        ->toContain("data-v65-add-product-focus @if(\$screenType !== 'quote')")
        ->and($css)->toContain('.v65-quote-products-grid .v65-reference-products-title{margin-bottom:10px}')
        ->toContain('.v65-quote-products-grid .v65-reference-quick-product{display:block}')
        ->toContain('align-items:start;column-gap:9px;row-gap:8px')
        ->toContain('min-height:36px;height:36px');
});

it('supports quote-only VAT inclusive or exclusive unit-price entry without changing net accounting', function (): void {
    $quotes = file_get_contents(base_path('app/Livewire/Sales/QuoteEditor.php'));
    $toolbar = file_get_contents(base_path('resources/views/components/v65/document-prototype-toolbar.blade.php'));
    $sales = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $engine = file_get_contents(base_path('app/Actions/Documents/CalculateDocumentTotals.php'));

    expect($toolbar)->toContain("@if(\$screenType === 'quote')")
        ->toContain('wire:model.live="uiMeta.quote_price_mode"')
        ->toContain('KDV Hariç')
        ->toContain('KDV Dahil')
        ->and($sales)->toContain('setQuoteVatIncludedPrice(')
        ->toContain('quoteVatIncludedPrice($line)')
        ->toContain("(\$uiMeta['quote_price_mode'] ?? 'exclusive') === 'inclusive'")
        ->toContain("wire:model.blur=\"lines.{{ \$index }}.unit_price\"")
        ->and($quotes)->toContain('function setQuoteVatIncludedPrice(')
        ->toContain('function quoteVatIncludedPrice(')
        ->toContain("bcadd('100', \$rate, 4)")
        ->toContain("['unit_price'] = bcdiv(")
        ->toContain("['price_source'] = 'manual'")
        ->toContain('quotes.update')
        ->and($engine)->toContain("\$unitPrice = bcadd(\$line['unit_price'], '0', 4)");
});

it('computes a quote gross unit price with BCMath without touching the stored net amount', function (): void {
    $component = new \App\Livewire\Sales\QuoteEditor;
    $line = ['unit_price' => '100.0000', 'vat_rate' => '20.0000'];

    expect($component->quoteVatIncludedPrice($line))->toBe('120.0000')
        ->and($line['unit_price'])->toBe('100.0000')
        ->and($component->quoteVatIncludedPrice(['unit_price' => '100.0000', 'vat_rate' => '0']))
        ->toBe('100.0000');
});
