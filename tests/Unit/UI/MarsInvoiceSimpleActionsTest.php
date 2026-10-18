<?php

it('uses simple edit, delete and save controls for sales and purchase invoices', function (): void {
    $heading = file_get_contents(base_path('resources/views/components/v65/document-prototype-heading.blade.php'));
    $footer = file_get_contents(base_path('resources/views/components/v65/document-prototype-footer.blade.php'));
    foreach (['sales_invoice', 'supplier_invoice'] as $type) {
        expect($heading)->toContain($type);
    }
    expect($heading)->toContain('wire:click="editInvoice"')
        ->not->toContain('wire:click="deleteInvoice"')
        ->toContain('Faturayı Kaydet')
        ->and($footer)->toContain("['sales_invoice', 'supplier_invoice']");
    $sale = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $purchase = file_get_contents(base_path('resources/views/livewire/purchases/document-editor.blade.php'));
    expect($sale)->toContain('! ($this instanceof \\App\\Livewire\\Sales\\SalesInvoiceEditor)')
        ->toContain('wire:click="deleteInvoice"')
        ->toContain('<x-v65.record-delete-footer>')
        ->and($purchase)->toContain('! ($this instanceof \\App\\Livewire\\Purchases\\SupplierInvoiceEditor)')
        ->toContain('wire:click="deleteInvoice"')
        ->toContain('<x-v65.record-delete-footer>');
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
        ->toContain('ManageInvoiceRecord::class')
        ->toContain('riskAccepted')
        ->and($purchase)->toContain('parent::save($action)')
        ->toContain('ManageInvoiceRecord::class')
        ->toContain('deviationAccepted')
        ->and($saleView)->toContain('wire:model="riskAccepted"')
        ->and($purchaseView)->toContain('wire:model="deviationAccepted"');
});

it('keeps invoice edit and delete inside document detail rather than lists', function (): void {
    $trait = file_get_contents(base_path('app/Livewire/Concerns/WithV65DocumentList.php'));
    $blade = file_get_contents(base_path('resources/views/livewire/sales/document-list.blade.php'));
    expect($trait)->toContain('public function editInvoiceFromList(')
        ->toContain('public function deleteInvoiceFromList(')
        ->toContain("DocumentType::SalesInvoice, DocumentType::SupplierInvoice")
        ->and($blade)->toContain('route($detailRoute, [\'id\' => $document->id])')
        ->not->toContain('wire:click="editInvoiceFromList(')
        ->not->toContain('wire:click="deleteInvoiceFromList(')
        ->toContain('Görüntüleme yetkisi yok');
});

it('does not reverse a posted invoice just because its editor was opened', function (): void {
    $actions = file_get_contents(base_path('app/Actions/Documents/ManageInvoiceRecord.php'));
    $copy = file_get_contents(base_path('app/Actions/Documents/CreateEditableDocumentCopy.php'));
    expect($actions)->toContain('allowPostedInvoiceEdit: true')
        ->toContain('public function save(Document $draft')
        ->toContain("'edit_copy_of'")
        ->toContain('DB::connection(\'period\')->transaction')
        ->toContain('revision-reverse')
        ->and($copy)->toContain('bool $allowPostedInvoiceEdit = false');
});

it('starts new sales and purchase invoices with Vadeli payment and preserves saved choices', function (): void {
    $sales = file_get_contents(base_path('app/Livewire/Sales/SalesInvoiceEditor.php'));
    $purchase = file_get_contents(base_path('app/Livewire/Purchases/SupplierInvoiceEditor.php'));

    foreach ([$sales, $purchase] as $editor) {
        expect($editor)->toContain("\$this->uiMeta['payment_method'] ??= 'Vadeli';")
            ->toContain("\$this->uiMeta['payment_method'] = 'Vadeli';")
            ->toContain("\$this->document === null || \$this->document->status === 'draft'");
    }

    $salesView = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $purchaseView = file_get_contents(base_path('resources/views/livewire/purchases/document-editor.blade.php'));
    expect($salesView)->toContain('wire:model="uiMeta.payment_method"')
        ->and($purchaseView)->toContain('wire:model="uiMeta.payment_method"');
});
