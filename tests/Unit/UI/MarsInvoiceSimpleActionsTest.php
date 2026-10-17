<?php

it('uses simple edit, delete and save controls for sales and purchase invoices', function (): void {
    $heading = file_get_contents(base_path('resources/views/components/v65/document-prototype-heading.blade.php'));
    $footer = file_get_contents(base_path('resources/views/components/v65/document-prototype-footer.blade.php'));
    foreach (['sales_invoice', 'supplier_invoice'] as $type) {
        expect($heading)->toContain($type);
    }
    expect($heading)->toContain('wire:click="editInvoice"')
        ->toContain('wire:click="deleteInvoice"')
        ->toContain('Faturayı Kaydet')
        ->and($footer)->toContain("['sales_invoice', 'supplier_invoice']");
    $sale = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $purchase = file_get_contents(base_path('resources/views/livewire/purchases/document-editor.blade.php'));
    expect($sale)->toContain('! ($this instanceof \\App\\Livewire\\Sales\\SalesInvoiceEditor)')
        ->and($purchase)->toContain('! ($this instanceof \\App\\Livewire\\Purchases\\SupplierInvoiceEditor)');
});

it('keeps posted invoice deletion as audited reversal and editing as reversal plus editable draft', function (): void {
    $actions = file_get_contents(base_path('app/Actions/Documents/ManageInvoiceRecord.php'));
    $list = file_get_contents(base_path('app/Livewire/Concerns/WithV65DocumentList.php'));
    expect($actions)->toContain("MutationAuthorizer::authorize(\$permission.'.cancel')")
        ->toContain('CreateEditableDocumentCopy')
        ->toContain('ReverseDocument')
        ->toContain("status === 'draft'")
        ->toContain('invoice_draft_deleted')
        ->toContain('document.reverse')
        ->and($list)->toContain("whereDoesntHave('incomingRelations'")
        ->toContain("whereDoesntHave('outgoingRelations'");
});
