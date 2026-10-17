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
        ->toContain("hash('sha256', \$key.':delete-reversal')")
        ->and($list)->toContain("whereDoesntHave('incomingRelations'")
        ->toContain("whereDoesntHave('outgoingRelations'");
});

it('posts invoices from the single Save button without bypassing risk or deviation checks', function (): void {
    $sales = file_get_contents(base_path('app/Livewire/Sales/SalesInvoiceEditor.php'));
    $purchase = file_get_contents(base_path('app/Livewire/Purchases/SupplierInvoiceEditor.php'));
    $saleView = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $purchaseView = file_get_contents(base_path('resources/views/livewire/purchases/document-editor.blade.php'));
    expect($sales)->toContain('parent::save($action)')
        ->toContain('$this->post(app(PostSalesInvoice::class))')
        ->toContain('riskAccepted')
        ->and($purchase)->toContain('parent::save($action)')
        ->toContain('$this->post(app(PostSupplierInvoice::class))')
        ->toContain('deviationAccepted')
        ->and($saleView)->toContain('wire:model="riskAccepted"')
        ->and($purchaseView)->toContain('wire:model="deviationAccepted"');
});

it('exposes one-click invoice list edit and delete while preserving normal lists', function (): void {
    $trait = file_get_contents(base_path('app/Livewire/Concerns/WithV65DocumentList.php'));
    $blade = file_get_contents(base_path('resources/views/livewire/sales/document-list.blade.php'));
    expect($trait)->toContain('public function editInvoiceFromList(')
        ->toContain('public function deleteInvoiceFromList(')
        ->toContain("DocumentType::SalesInvoice, DocumentType::SupplierInvoice")
        ->and($blade)->toContain('wire:click="editInvoiceFromList(')
        ->toContain('wire:click="deleteInvoiceFromList(')
        ->toContain('Görüntüleme yetkisi yok');
});
