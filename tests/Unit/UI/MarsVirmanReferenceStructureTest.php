<?php

it('renders the approved virman form, separate before-after balances and three financial totals', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/finance/contact-transfer-form.blade.php'));
    foreach ([
        'Virman Bilgileri', 'Kaynak Cari *', 'Hedef Cari *',
        'Virman No', 'Virman Tarihi *', 'Aktarılacak Bakiye Türü',
        'Para Birimi', 'Aktarılacak Tutar *', 'Referans / Belge No',
        'İşlem Öncesi / Sonrası Bakiyeler', 'Toplam Cari Bakiye Değişimi',
        'Banka / Kasa Etkisi', 'Cari Virman Geçmişi',
        'wire:click="clearForm"', 'wire:click="post"',
    ] as $part) {
        expect($view)->toContain($part);
    }
    expect($view)->toContain('canViewContactBalances()')
        ->toContain('Görüntüleme yetkisi yok')
        ->toContain('showOverdraftConfirm')
        ->toContain('contact_transfers.override_balance');
});

it('will not clear a correction form before the reversals have been handled', function (): void {
    $form = file_get_contents(base_path('app/Livewire/Finance/ContactTransferForm.php'));
    expect($form)->toContain('public function clearForm(): void')
        ->toContain("abort_if(\$this->correctionSourceId !== null, 409)")
        ->toContain("auth()->user()?->can('contact_transfers.create')");
});
