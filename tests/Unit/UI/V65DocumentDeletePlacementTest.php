<?php

it('keeps invoice deletion in the bottom-left details footer behind an arithmetic challenge', function (): void {
    $heading = file_get_contents(base_path('resources/views/components/v65/document-prototype-heading.blade.php'));
    expect($heading)->not->toContain('wire:click="deleteInvoice"');

    foreach (['sales', 'purchases'] as $module) {
        $view = file_get_contents(base_path("resources/views/livewire/{$module}/document-editor.blade.php"));
        expect($view)->toContain('<x-v65.record-delete-footer>')
            ->toContain("requestRecordDeletion('deleteInvoice')")
            ->toContain("livewire.partials.record-delete-confirmation")
            ->not->toContain('wire:click="deleteInvoice"')
            ->toContain('DocumentEditCatalog::hasReversal($document)');
    }
});

it('protects every existing delete footer and requires verification inside server handlers', function (): void {
    $contracts = [
        'finance/collection-form.blade.php' => ['deleteRecord'],
        'purchases/payment-form.blade.php' => ['deleteRecord'],
        'finance/contact-debit-credit-form.blade.php' => ['deleteRecord'],
        'finance/contact-transfer-form.blade.php' => ['deleteRecord'],
        'finance/finance-operation-center.blade.php' => [
            'deleteFinanceDocument', 'deleteManualRecord', 'deleteTransferRecord',
        ],
        'finance/securities-center.blade.php' => ['deletePayroll'],
        'returns/return-center.blade.php' => ['deleteReturn'],
        'pages/stock/warehouse-slip-detail.blade.php' => ['reverse'],
    ];

    foreach ($contracts as $path => $methods) {
        $view = file_get_contents(base_path('resources/views/livewire/'.$path));
        expect($view)->toContain('<x-v65.record-delete-footer>')
            ->toContain("livewire.partials.record-delete-confirmation");
        foreach ($methods as $method) {
            expect($view)->toContain("requestRecordDeletion('{$method}'")
                ->not->toContain('wire:click="'.$method.'(');
        }
    }

    $sources = [
        'Sales/SalesInvoiceEditor.php' => ['deleteInvoice'],
        'Purchases/SupplierInvoiceEditor.php' => ['deleteInvoice'],
        'Finance/CollectionForm.php' => ['deleteRecord'],
        'Purchases/PaymentForm.php' => ['deleteRecord'],
        'Finance/ContactDebitCreditForm.php' => ['deleteRecord'],
        'Finance/ContactTransferForm.php' => ['deleteRecord'],
        'Finance/FinanceOperationCenter.php' => [
            'deleteFinanceDocument', 'deleteManualRecord', 'deleteTransferRecord',
        ],
        'Finance/SecuritiesCenter.php' => ['deletePayroll'],
        'Returns/ReturnCenter.php' => ['deleteReturn'],
        'Pages/Stock/WarehouseSlipDetail.php' => ['reverse'],
    ];
    foreach ($sources as $file => $methods) {
        $source = file_get_contents(base_path('app/Livewire/'.$file));
        foreach ($methods as $method) {
            expect($source)->toContain('requireVerifiedRecordDeletion(\''.$method.'\')');
        }
    }
});

it('stores challenge and action in locked Livewire state, limits attempts and expiry', function (): void {
    $trait = file_get_contents(base_path('app/Livewire/Concerns/WithVerifiedRecordDeletion.php'));
    $modal = file_get_contents(base_path('resources/views/livewire/partials/record-delete-confirmation.blade.php'));
    expect($trait)->toContain('#[Locked]')
        ->toContain('random_int(')
        ->toContain('time() + 180')
        ->toContain('$this->recordDeleteFailures >= 3')
        ->toContain('requireVerifiedRecordDeletion(')
        ->toContain('protected ?string $recordDeletionAuthorizedMethod = null')
        ->toContain('finally')
        ->and($modal)->toContain('wire:submit="confirmRecordDeletion"')
        ->toContain('wire:model="recordDeleteAnswer"')
        ->toContain('wire:click="cancelRecordDeletion"')
        ->toContain('Silme İşlemini Onayla');

    $css = file_get_contents(base_path('resources/css/v65-shell.css'));
    expect($css)->toContain('.v65-record-delete-footer')
        ->toContain('justify-content:flex-start')
        ->toContain('.v65-record-delete-overlay');
});
