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
