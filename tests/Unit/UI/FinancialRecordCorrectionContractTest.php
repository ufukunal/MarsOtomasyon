<?php

it('opens posted payment and collection records only through a ledger-safe reversal flow', function (): void {
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
            ->toContain('abort_if($this->correctionMode')
            ->toContain($permission)
            ->toContain('$this->financialCorrectionReversed(');
    }
});

it('asks for confirmation before reversing a posted financial record', function (): void {
    foreach ([
        'finance/collection-form.blade.php',
        'purchases/payment-form.blade.php',
    ] as $path) {
        $view = file_get_contents(base_path('resources/views/livewire/'.$path));
        expect($view)->toContain('Kayıtları Aç / Düzenle')
            ->toContain('selectForCorrection(')
            ->toContain('data-v65-modal-open=')
            ->toContain('Ters Kayıt Onayı')
            ->toContain('wire:click="reverse"')
            ->toContain('contactBalance()');
    }
});
