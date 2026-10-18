<?php

it('opens posted payment and collection records for atomic audited replacement', function (): void {
    $trait = file_get_contents(base_path('app/Livewire/Concerns/WithFinancialRecordCorrection.php'));

    expect($trait)->toContain("where('status', 'posted')")
        ->toContain("where('relation_type', 'reversal_of')")
        ->toContain('public function selectForCorrection(')
        ->toContain('public function financialCorrectionReversed(')
        ->toContain('CashMovement::query()')
        ->toContain('BankMovement::query()');

    foreach ([
        'Finance/CollectionForm.php' => 'collections.cancel',
        'Purchases/PaymentForm.php' => 'payments.cancel',
    ] as $file => $permission) {
        $source = file_get_contents(base_path('app/Livewire/'.$file));
        expect($source)->toContain('use WithFinancialRecordCorrection;')
            ->toContain("DB::connection('period')->transaction")
            ->toContain('deleteRecord(int $id)')
            ->toContain($permission)
            ->toContain('$this->financialCorrectionPosted()');
    }
});

it('shows one-step Save, Edit and Delete without reversal confirmation dialogs', function (): void {
    foreach ([
        'finance/collection-form.blade.php',
        'purchases/payment-form.blade.php',
    ] as $path) {
        $view = file_get_contents(base_path('resources/views/livewire/'.$path));
        expect($view)->toContain('Kayıtları Aç / Düzenle')
            ->toContain('selectForCorrection(')
            ->toContain('wire:click="deleteRecord(')
            ->toContain('wire:click="post"')
            ->not->toContain('Ters Kayıt Onayı')
            ->toContain('contactBalance()');
    }
});


it('does not allow a collection correction to reverse a payment or vice versa', function (): void {
    foreach (['Finance/CollectionForm.php', 'Purchases/PaymentForm.php'] as $file) {
        $source = file_get_contents(base_path('app/Livewire/'.$file));
        expect($source)->toContain("->where('document_type', \$this->financialDocumentType()->value)")
            ->toContain("->where('status', 'posted')")
            ->toContain('->findOrFail($this->postedDocumentId)');
    }
});


it('validates voucher posting and retains aligned V65 screens', function (): void {
    foreach (['Finance/CollectionForm.php', 'Purchases/PaymentForm.php'] as $file) {
        $source = file_get_contents(base_path('app/Livewire/'.$file));
        expect($source)->toContain("'contactId' => ['required', 'integer', 'min:1']")
            ->toContain("'amount' => ['required', 'numeric', 'gt:0']")
            ->toContain("'accountType' => ['required', 'in:cash,bank']");
    }
    foreach (['finance/collection-form.blade.php', 'purchases/payment-form.blade.php'] as $file) {
        $blade = file_get_contents(base_path('resources/views/livewire/'.$file));
        expect($blade)->toContain('v65-voucher-panel')
            ->toContain('wire:key="account-{{ $accountType }}"')
            ->toContain('wire:model.live="contactId"');
    }
});


it('styles payment and collection with the same responsive V65 voucher composition', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));
    foreach ([
        'finance/collection-form.blade.php',
        'purchases/payment-form.blade.php',
    ] as $path) {
        $view = file_get_contents(base_path('resources/views/livewire/'.$path));
        expect($view)->toContain('class="panel stack v65-voucher-panel"')
            ->toContain('class="panel v65-financial-history"')
            ->toContain('v65-contact-transfer-balance');
    }
    expect($css)->toContain('.v65-workspace-form>.v65-voucher-panel')
        ->toContain('.v65-financial-history')
        ->toContain('@media(max-width:720px)')
        ->toContain('grid-template-columns:minmax(0,1fr)');
});
