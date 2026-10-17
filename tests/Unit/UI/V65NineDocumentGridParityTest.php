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
