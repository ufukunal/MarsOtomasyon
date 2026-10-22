<?php

it('uses the same V65 editor structure for sales and purchasing documents', function (): void {
    $sales = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $purchase = file_get_contents(base_path('resources/views/livewire/purchases/document-editor.blade.php'));

    foreach ([$sales, $purchase] as $blade) {
        expect($blade)->toContain('<x-v65.document-prototype-heading')
            ->toContain('<x-v65.document-readonly')
            ->toContain('<x-v65.document-summary')
            ->toContain('<x-v65.document-prototype-footer')
            ->toContain('v65-doc-vat-toolbar')
            ->toContain('v65-doc-lines')
            ->toContain('v65-reference-totals-layout')
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
            ->toContain("'line_total' => (string) ".'$line->line_total')
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

it('keeps draft printing consistent with the saved document and Blade PHP syntax compatible', function (): void {
    foreach ([
        'resources/views/livewire/sales/document-editor.blade.php',
        'resources/views/livewire/purchases/document-editor.blade.php',
    ] as $view) {
        $source = file_get_contents(base_path($view));
        expect($source)->not->toContain('@php(')
            ->and($source)->toContain('@php'."\n".'        $draftPreview = $this->draftTotalsPreview();')
            ->toContain('<div class="v65-doc-print-only">')
            ->toContain('<x-v65.document-readonly :document="$document" />')
            ->toContain('<x-v65.document-summary :document="$document" />')
            ->toContain('class="v65-doc-screen-summary"')
            ->toContain(':preview="$draftPreview"');
    }

    $css = file_get_contents(base_path('resources/css/v65-shell.css'));
    expect($css)->toContain('.v65-doc-print-only ~ .v65-doc-screen-summary')
        ->toContain('.v65-doc-print-only > .v65-doc-summary');
});

it('preserves manually priced sales lines when the customer changes', function (): void {
    $editor = file_get_contents(base_path('app/Livewire/Sales/BaseSalesDocumentEditor.php'));
    $view = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));

    expect($editor)->toContain("if (\$property === 'contactId')")
        ->toContain('$this->refreshContactPrices();')
        ->toContain("['price_source'] = 'manual'")
        ->toContain("'price_source' => 'manual'")
        ->toContain("'price_source' => 'auto'")
        ->toContain("(\$line['price_source'] ?? 'manual') !== 'auto'")
        ->toContain('private function refreshContactPrices(bool $includeManual = false): void')
        ->toContain('$this->refreshContactPrices(true);')
        ->toContain('public function applyContactPrices(): void')
        ->and($view)->toContain('wire:click="applyContactPrices"')
        ->toContain('Manuel girdiğiniz satır fiyatları')
        ->toContain('wire:loading.attr="disabled"');
});

it('shows permission-aware source documents when editing saved drafts', function (): void {
    $sources = file_get_contents(base_path('resources/views/components/v65/document-sources.blade.php'));
    $readonly = file_get_contents(base_path('resources/views/components/v65/document-readonly.blade.php'));

    foreach ([
        'resources/views/livewire/sales/document-editor.blade.php',
        'resources/views/livewire/purchases/document-editor.blade.php',
    ] as $path) {
        $blade = file_get_contents(base_path($path));
        expect($blade)->toContain('<x-v65.document-sources');
    }

    expect($sources)->toContain('incomingRelations')
        ->toContain('auth()->user()?->can(')
        ->toContain('sourceDocument')
        ->and($readonly)->toContain('<x-v65.document-sources :document="$document" />');
});

it('shares the default product choices across rows while preserving targeted search', function (): void {
    foreach ([
        'app/Livewire/Sales/BaseSalesDocumentEditor.php',
        'app/Livewire/Purchases/BasePurchaseDocumentEditor.php',
    ] as $path) {
        $editor = file_get_contents(base_path($path));

        expect($editor)->toContain('private ?Collection $defaultProductOptions = null;')
            ->toContain('$this->defaultProductOptions ??=')
            ->toContain('clone $this->defaultProductOptions')
            ->toContain('public function productOptionsForLine(')
            ->toContain("->orWhere('barcode', 'ilike',");
    }
});

it('preserves the standalone Goods Receipt presentation while adopting eight reference screens', function (): void {
    $purchase = file_get_contents(base_path('resources/views/livewire/purchases/document-editor.blade.php'));
    $sale = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $heading = file_get_contents(base_path('resources/views/components/v65/document-prototype-heading.blade.php'));

    expect($purchase)->toContain('@if($isGoodsReceipt)')
        ->toContain('<x-v65.document-heading')
        ->toContain('Mal Kabul Kalemleri')
        ->and($sale)->toContain('<x-v65.document-prototype-heading')
        ->and($heading)->toContain("route('documents.print'")
        ->toContain('type="submit"')
        ->toContain('wire:target="save"');
});

it('uses one searchable quote contact field and retains legacy pickers for other sales documents', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $editor = file_get_contents(base_path('app/Livewire/Sales/BaseSalesDocumentEditor.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    $quoteStart = strpos($view, '@php($selectedQuoteContact = $this->selectedQuoteContact())');
    $quoteEnd = strpos($view, "\n            @else\n            <div class=\"v65-doc-contact-picker\">", $quoteStart);
    expect($quoteStart)->not->toBeFalse();
    expect($quoteEnd)->not->toBeFalse();
    $quotePicker = substr($view, $quoteStart, $quoteEnd - $quoteStart);
    expect($quotePicker)->not->toContain('<x-v65.select')
        ->not->toContain('wire:model.live="contactId"')
        ->toContain('v65-quote-contact-picker');

    expect($view)->toContain("@if(\$screenType === 'quote')")
        ->toContain('v65-quote-contact-picker')
        ->toContain('wire:model.live.debounce.300ms="contactSearch"')
        ->toContain('wire:click="selectQuoteContact(')
        ->toContain('wire:click="clearQuoteContact"')
        ->toContain('quoteContactResults()')
        ->toContain('selectedQuoteContact()')
        ->toContain('x-on:keydown.arrow-down.prevent=')
        ->toContain('<x-v65.input-error field="contactId" />')
        ->toContain('<x-v65.select label="Cari" wire:model.live="contactId"')
        ->and($editor)->toContain('function selectQuoteContact(int $contactId)')
        ->toContain('function clearQuoteContact()')
        ->toContain("where('is_active', true)->findOrFail(\$contactId)")
        ->toContain("abort_unless(\$this->documentType() === DocumentType::Quote, 404)")
        ->toContain("\$this->updated('contactId')")
        ->toContain('function quoteContactResults(): Collection')
        ->toContain('mb_strlen($search) < 2')
        ->and($css)->toContain('.v65-reference-info-fields>.v65-quote-contact-picker')
        ->toContain('.v65-quote-contact-results');
});

it('guards quote single-field contact selection in UAT images and supports Enter selection', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $deploy = file_get_contents(base_path('ops/uat-fresh-web-update.sh'));

    expect($view)->toContain('data-v65-single-contact="quote"')
        ->toContain('x-on:keydown.enter.prevent="$refs.results?.querySelector(\'button\')?.click()"')
        ->and($deploy)->toContain('QUOTE_CONTACT_UI_FAIL')
        ->toContain('single search field missing from deployed quote template');

    // Already pinned image, image validation and running-container checks.
    expect(substr_count($deploy, 'QUOTE_CONTACT_UI_FAIL'))->toBe(3);
    expect(substr_count($deploy, 'grep -Fq "data-v65-single-contact=\"quote\""'))->toBe(3);
});
