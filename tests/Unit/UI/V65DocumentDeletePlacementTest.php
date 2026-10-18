<?php

it('positions invoice delete below document details, not in the heading', function (): void {
    $heading = file_get_contents(base_path('resources/views/components/v65/document-prototype-heading.blade.php'));
    $component = file_get_contents(base_path('resources/views/components/v65/record-delete-footer.blade.php'));
    expect($heading)->not->toContain('wire:click="deleteInvoice"');
    expect($component)->toContain('v65-record-delete-footer');

    foreach (['sales', 'purchases'] as $module) {
        $view = file_get_contents(base_path("resources/views/livewire/{$module}/document-editor.blade.php"));
        expect($view)->toContain('<x-v65.record-delete-footer>')
            ->toMatch('/<x-v65\\.record-delete-footer>[\\s\\S]*?wire:click="deleteInvoice"[\\s\\S]*?<\\/x-v65\\.record-delete-footer>/')
            ->toContain('DocumentEditCatalog::hasReversal($document)');
    }
});

it('keeps permitted detail-only delete actions inside the shared left footer', function (): void {
    $actions = [
        'finance/collection-form.blade.php' => 'deleteRecord',
        'purchases/payment-form.blade.php' => 'deleteRecord',
        'finance/contact-debit-credit-form.blade.php' => 'deleteRecord',
        'finance/contact-transfer-form.blade.php' => 'deleteRecord',
        'finance/finance-operation-center.blade.php' => 'deleteFinanceDocument',
        'finance/securities-center.blade.php' => 'deletePayroll',
        'returns/return-center.blade.php' => 'deleteReturn',
        'pages/stock/warehouse-slip-detail.blade.php' => 'reverse',
    ];
    foreach ($actions as $path => $method) {
        $view = file_get_contents(base_path('resources/views/livewire/'.$path));
        expect($view)->toContain('<x-v65.record-delete-footer>')
            ->toMatch('/<x-v65\\.record-delete-footer>[\\s\\S]*?wire:click="'.preg_quote($method, '/').'(?:\\(|")[\\s\\S]*?<\\/x-v65\\.record-delete-footer>/');
    }

    $finance = file_get_contents(base_path('resources/views/livewire/finance/finance-operation-center.blade.php'));
    expect($finance)->toContain('wire:click="deleteManualRecord(')
        ->toContain('wire:click="deleteTransferRecord(');
    $styles = file_get_contents(base_path('resources/css/v65-shell.css'));
    expect($styles)->toContain('.v65-record-delete-footer')
        ->toContain('justify-content:flex-start');
});
