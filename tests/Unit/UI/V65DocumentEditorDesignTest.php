<?php

it('uses the same V65 editor structure for sales and purchasing documents', function (): void {
    $sales = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $purchase = file_get_contents(base_path('resources/views/livewire/purchases/document-editor.blade.php'));

    foreach ([$sales, $purchase] as $blade) {
        expect($blade)->toContain('<x-v65.document-heading')
            ->toContain('<x-v65.document-readonly')
            ->toContain('<x-v65.document-summary')
            ->toContain('<x-v65.form-actions')
            ->toContain('v65-doc-vat-toolbar')
            ->toContain('v65-doc-lines')
            ->toContain('v65-doc-form-actions')
            ->toContain('wire:submit="save"')
            ->toContain('wire:model.live="contactId"')
            ->toContain('wire:model.live.debounce.300ms="contactSearch"')
            ->toContain('wire:model.live.debounce.300ms="productSearches.')
            ->toContain('productOptionsForLine(')
            ->toContain('wire:model.live="lines.{{ $index }}.product_id"')
            ->toContain('wire:model.live="lines.{{ $index }}.line_kind"')
            ->toContain('wire:model.blur="lines.{{ $index }}.quantity"')
            ->toContain('wire:model.blur="lines.{{ $index }}.unit_price"')
            ->toContain('data-tr-decimal')
            ->toContain('lineTotal')
            ->toContain('row_key')
            ->toContain('wire:loading.attr="disabled"')
            ->toContain('$documentDirty')
            ->toContain('$this->draftTotalsPreview()');
    }
});

it('shows non-editable document lines with real stored amounts in the correct currency', function (): void {
    $heading = file_get_contents(base_path('resources/views/components/v65/document-heading.blade.php'));
    $readonly = file_get_contents(base_path('resources/views/components/v65/document-readonly.blade.php'));
    $summary = file_get_contents(base_path('resources/views/components/v65/document-summary.blade.php'));

    expect($heading)->toContain("'draft' => 'Taslak'")
        ->toContain("'posted' => 'Kesinleşti'")
        ->and($readonly)->toContain('$document->lines')
        ->toContain('$line->line_total')
        ->toContain('$line->unit?->code')
        ->toContain('Satır net tutarları KDV hariçtir')
        ->and($summary)->toContain('Taslak hesap önizlemesi')
        ->toContain('$source->vatAmount')
        ->toContain('$source->grandTotal')
        ->toContain('$source->grand_total');
});

it('tracks unsaved edits and guards document workflow transitions without changing posting services', function (): void {
    foreach ([
        'app/Livewire/Sales/BaseSalesDocumentEditor.php',
        'app/Livewire/Purchases/BasePurchaseDocumentEditor.php',
    ] as $path) {
        $code = file_get_contents(base_path($path));
        expect($code)->toContain('public bool $documentDirty = false')
            ->toContain('public function updated(string $property)')
            ->toContain('public function draftTotalsPreview()')
            ->toContain('CalculateDocumentTotals::class')
            ->toContain('public function productOptionsForLine(')
            ->toContain('public function contactOptions()')
            ->toContain("['row_key']")
            ->toContain("'line_total' => (string) $line->line_total")
            ->toContain('protected function requireSavedDraft(): void')
            ->toContain('ValidationException::withMessages');
    }

    foreach ([
        'app/Livewire/Sales/SalesInvoiceEditor.php',
        'app/Livewire/Sales/DispatchEditor.php',
        'app/Livewire/Sales/SalesOrderEditor.php',
        'app/Livewire/Sales/QuoteEditor.php',
        'app/Livewire/Purchases/SupplierInvoiceEditor.php',
        'app/Livewire/Purchases/GoodsReceiptEditor.php',
        'app/Livewire/Purchases/PurchaseOrderEditor.php',
    ] as $path) {
        expect(file_get_contents(base_path($path)))->toContain('$this->requireSavedDraft();');
    }
});

it('keeps generic print buttons off document editors and uses responsive document-only styling', function (): void {
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));
    $list = file_get_contents(base_path('resources/views/livewire/sales/document-list.blade.php'));

    expect($layout)->toContain('$v65DocumentEditorRoutes')
        ->toContain("'sales.invoices.edit'")
        ->toContain("'purchases.invoices.edit'")
        ->and($css)->toContain('V65 unified sales/purchase document editors')
        ->toContain('.v65-doc-editor')
        ->toContain('.v65-doc-contact-picker')
        ->toContain('.v65-doc-product-search')
        ->toContain('.v65-doc-summary')
        ->toContain('.v65-doc-print-only')
        ->toContain('@media print')
        ->and($list)->toContain('<x-v65.status-badge')
        ->toContain('<x-v65.empty-state');
});
