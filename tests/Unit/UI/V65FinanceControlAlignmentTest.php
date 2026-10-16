<?php

it('uses uniform finance entry controls in collection, payment and cheque/senet screens', function (): void {
    foreach ([
        'resources/views/livewire/finance/collection-form.blade.php',
        'resources/views/livewire/purchases/payment-form.blade.php',
        'resources/views/livewire/finance/securities-center.blade.php',
    ] as $file) {
        $view = file_get_contents(base_path($file));
        expect($view)->toContain('v65')
            ->toContain('inputmode="decimal"')
            ->toContain('data-tr-decimal');
    }

    $payment = file_get_contents(base_path('resources/views/livewire/purchases/payment-form.blade.php'));
    $security = file_get_contents(base_path('resources/views/livewire/finance/securities-center.blade.php'));
    expect($payment)->toContain('wire:model.live="accountType"')
        ->and($security)->not->toContain('<label>')
        ->toContain('<label class="field">');
});
