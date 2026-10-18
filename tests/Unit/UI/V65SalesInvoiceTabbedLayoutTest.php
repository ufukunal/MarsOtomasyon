<?php

it('shows invoice metadata tabs above permanent product movements and a separate bottom totals panel', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));

    expect($view)->toContain("x-data=\"{ invoiceTab: 'bilgiler' }\"")
        ->toContain('role="tablist" aria-label="Satış faturası üst bilgi sekmeleri"')
        ->toContain('>Bilgiler</button>')
        ->toContain('>Notlar</button>')
        ->toContain('>Diğer</button>')
        ->toContain('wire:model.blur="notes"')
        ->toContain('wire:model="uiMeta.payment_method"')
        ->toContain('wire:model.live="uiMeta.default_location_id"')
        ->toContain('wire:model.blur="discountRate"')
        ->toContain('wire:model.blur="uiMeta.customer_reference_no"')
        ->toContain('v65-invoice-bottom-totals');

    $metadata = strpos($view, 'class="v65-invoice-meta"');
    $movements = strpos($view, 'class="v65-doc-lines v65-reference-products-card"');
    $totals = strpos($view, 'class="v65-invoice-bottom-totals"');
    $formEnd = strpos($view, '</form>');
    expect($metadata)->toBeInt()
        ->and($movements)->toBeGreaterThan($metadata)
        ->and($totals)->toBeGreaterThan($movements)
        ->and($formEnd)->toBeGreaterThan($totals);

    // The table and its Livewire bindings stay outside the tab condition.
    $table = substr($view, $movements, $totals - $movements);
    expect($table)->toContain('data-v65-fast-product-search')
        ->toContain('wire:model.blur="lines.')
        ->toContain('v65-doc-table')
        ->not->toContain('x-show="invoiceTab');
});

it('preserves the other sales documents and the existing posting/accounting bindings', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $editor = file_get_contents(base_path('app/Livewire/Sales/BaseSalesDocumentEditor.php'));

    expect($view)->toContain("@if(\$screenType === 'sales_invoice')")
        ->toContain('@else'."\n".'            <section class="v65-reference-info-card"')
        ->toContain("x-show=\"invoiceTab === 'notlar'\"")
        ->toContain("x-show=\"invoiceTab === 'diger'\"")
        ->toContain("@if(\$screenType !== 'sales_invoice')")
        ->toContain('wire:submit="save"')
        ->toContain('<x-v65.document-summary class="v65-invoice-summary"')
        ->and($editor)->toContain("'notes' => \$this->notes")
        ->toContain("'ui_meta' => \$this->uiMeta")
        ->toContain("'discount_rate' => \$this->discountRate");
});

it('shows read-only contact information only with contact viewing permission', function (): void {
    $invoice = file_get_contents(base_path('app/Livewire/Sales/SalesInvoiceEditor.php'));
    $view = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));

    expect($invoice)->toContain('public function invoiceContactDetails(): ?array')
        ->toContain("can('contacts.view')")
        ->toContain("->with('categories')")
        ->toContain("'tax_office' =>")
        ->toContain("'tax_number' =>")
        ->toContain("'categories' =>")
        ->and($view)->toContain('$this->invoiceContactDetails()')
        ->toContain("{{ \$invoiceContact['title'] ?? '—' }}")
        ->toContain('Cari kartından alınır')
        ->toContain('v65-invoice-readonly-grid');
});

it('uses responsive invoice tabs without affecting purchase invoice styles', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));
    expect($css)->toContain('.v65-invoice-tabbed-form .v65-invoice-tabs')
        ->toContain('.v65-invoice-info-grid')
        ->toContain('grid-template-columns:repeat(4,minmax(0,1fr))')
        ->toContain('grid-template-columns:minmax(0,1fr)')
        ->toContain('.v65-invoice-tab-panel[x-cloak]')
        ->toContain('.v65-invoice-bottom-totals');
});


it('keeps sales invoice header compact with four columns and side-by-side contact summaries', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $style = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($view)->toContain('v65-invoice-contact-controls')
        ->toContain('v65-invoice-contact-panels')
        ->toContain('v65-invoice-readonly-grid')
        ->toContain('v65-invoice-readonly-item')
        ->toContain('wire:model.live.debounce.300ms="contactSearch"')
        ->toContain('wire:model.live="contactId"')
        ->toContain('wire:click="applyContactPrices"')
        ->toContain('wire:model="documentDate"')
        ->toContain('wire:model="dueDate"')
        ->toContain('wire:model="uiMeta.payment_method"')
        ->toContain('wire:model.live="uiMeta.default_location_id"')
        ->not->toContain('@php(')
        ->and($style)->toContain('grid-template-columns:repeat(4,minmax(0,1fr))')
        ->toContain('.v65-invoice-contact-panels')
        ->toContain('@media(max-width:760px)');
});


it('unifies invoice risk, item count and amounts into one compact bottom zone', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($view)->toContain('v65-invoice-bottom-zone')
        ->toContain('<details class="v65-invoice-risk-details')
        ->toContain('<x-v65.contact-risk-overview :risk="$draftRiskOverview" />')
        ->toContain('v65-invoice-totals-toolbar')
        ->toContain('{{ $lineCount }} kalem · Sunucu hesaplaması')
        ->toContain('wire:click="resetNewDraft"')
        ->toContain('v65-invoice-bottom-totals')
        ->toContain('wire:model="riskAccepted"')
        ->toContain('v65-invoice-summary')
        ->not->toContain('<x-v65.document-prototype-footer :document="$document" :screen-type="$screenType" :item-count="$lineCount" />'."\n".'                <section class="v65-invoice-bottom-totals"')
        ->and($css)->toContain('.v65-invoice-bottom-zone')
        ->toContain('.v65-invoice-risk-details')
        ->toContain('.v65-invoice-bottom-totals .v65-doc-summary-head{display:none}')
        ->toContain('grid-template-columns:repeat(2,minmax(0,1fr))');
});


it('inherits customer card discount for new sales invoices without overwriting existing drafts', function (): void {
    $invoice = file_get_contents(base_path('app/Livewire/Sales/SalesInvoiceEditor.php'));
    $base = file_get_contents(base_path('app/Livewire/Sales/BaseSalesDocumentEditor.php'));
    $contact = file_get_contents(base_path('app/Models/Period/Contact.php'));
    $view = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));

    expect($contact)->toContain("'discount_rate' => 'decimal:4'")
        ->and($invoice)->toContain('public function updatedContactId(): void')
        ->toContain('private function applySelectedContactDiscount(): void')
        ->toContain("->where('is_active', true)->whereKey(\$this->contactId)->value('discount_rate')")
        ->toContain("\$rate = (string) (\$contactRate ?? '0');")
        ->toContain("rtrim(rtrim(\$rate, '0'), '.')")
        ->toContain('if ($this->document === null && $this->contactId !== null)')
        ->toContain('$this->applySelectedContactDiscount();')
        ->and($base)->toContain('$this->discountRate = (string) $document->discount_rate;')
        ->toContain("'discount_rate' => \$this->discountRate")
        ->toContain("->handle(\$input, (string) \$this->discountRate")
        ->and($view)->toContain('aria-label="Satış faturası belge iskonto yüzdesi"')
        ->toContain('Cari kartından gelir; değiştirilebilir.')
        ->toContain('Belge No:')
        ->toContain('wire:model.blur="discountRate"');

    // New invoice: one editor only. No second discount field in the "Diğer" panel.
    $invoiceMeta = substr($view, strpos($view, 'class="v65-invoice-meta"'), strpos($view, 'id="v65-invoice-panel-notlar"') - strpos($view, 'class="v65-invoice-meta"'));
    $otherPanel = substr($view, strpos($view, 'id="v65-invoice-panel-diger"'), strpos($view, '            @else') - strpos($view, 'id="v65-invoice-panel-diger"'));
    expect(substr_count($invoiceMeta, 'wire:model.blur="discountRate"'))->toBe(1)
        ->and($otherPanel)->not->toContain('wire:model.blur="discountRate"');
});


it('formats sales invoice price display separately from four-decimal calculation state', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $script = file_get_contents(base_path('resources/js/app.js'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));
    $editor = file_get_contents(base_path('app/Livewire/Sales/BaseSalesDocumentEditor.php'));

    expect($view)->toContain('data-tr-decimal data-v65-money-display')
        ->toContain('data-v65-discount-display')
        ->toContain('title="Cari kartından gelir; değiştirilebilir."')
        ->toContain('wire:model.blur="discountRate"')
        ->and($script)->toContain('v65InvoiceMoneyFormatter')
        ->toContain('minimumFractionDigits: 2')
        ->toContain('maximumFractionDigits: 2')
        ->toContain('v65DisplayRaw')
        ->toContain("input.dataset.v65InputEdited !== 'true'")
        ->toContain('input.value = input.dataset.v65DisplayRaw')
        ->toContain("input.dataset.v65DiscountEdited === 'true'")
        ->toContain('event.isTrusted')
        ->toContain("Livewire.hook('morph.updated'")
        ->and($editor)->toContain("'unit_price' => \$price")
        ->toContain("'discount_rate' => \$this->discountRate")
        ->and($css)->toContain('.v65-invoice-tabbed-form .v65-reference-products-tools')
        ->toContain('.v65-invoice-info-grid>.v65-invoice-discount-field');
});
