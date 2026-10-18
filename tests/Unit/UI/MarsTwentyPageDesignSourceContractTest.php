<?php

it('keeps the nine approved sales and purchase layouts while preserving their posting actions', function (): void {
    foreach (['sales', 'purchases'] as $side) {
        $view = file_get_contents(base_path("resources/views/livewire/{$side}/document-editor.blade.php"));
        expect($view)->toContain('v65-reference-info-card')
            ->toContain('v65-reference-products-card')
            ->toContain('v65-reference-totals-layout')
            ->toContain('wire:submit="save"')
            ->toContain('wire:model.live="uiMeta.default_location_id"');
    }
    $sales = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $purchases = file_get_contents(base_path('resources/views/livewire/purchases/document-editor.blade.php'));
    foreach (['Teslim Süresi', 'Ödeme Şartı', 'Planlanan Teslim', 'Ödeme Şekli'] as $field) {
        expect($sales)->toContain($field);
    }
    foreach (['Teklif Geçerlilik', 'Planlanan Kabul Deposu', 'Kaynak Sipariş', 'İrsaliye No', 'Kabul Tarihi'] as $field) {
        expect($purchases)->toContain($field);
    }
    expect($purchases)->toContain('v65-goods-receipt-reference')
        ->toContain('<x-v65.document-heading')
        ->toContain('Mal Kabul Kalemleri');
});

it('keeps the three independent finance entry screens, secure reversal and separate summary cards', function (): void {
    $collection = file_get_contents(base_path('resources/views/livewire/finance/collection-form.blade.php'));
    $payment = file_get_contents(base_path('resources/views/livewire/purchases/payment-form.blade.php'));
    $transfer = file_get_contents(base_path('resources/views/livewire/finance/contact-transfer-form.blade.php'));
    foreach ([$collection, $payment] as $view) {
        expect($view)->toContain('v65-ref-summary-card')
            ->toContain('Cari Hareket Özeti')
            ->toContain('v65-finance-impact')
            ->toContain('wire:click="post"');
    }
    foreach (['Virman Bilgileri', 'Virman Tarihi *', 'Aktarılacak Bakiye Türü', 'Referans / Belge No',
        'İşlem Öncesi / Sonrası Bakiyeler', 'Toplam Cari Bakiye Değişimi', 'Banka / Kasa Etkisi',
        'Cari Virman Geçmişi', 'showOverdraftConfirm', 'wire:click="deleteRecord"'] as $marker) {
        expect($transfer)->toContain($marker);
    }
});

it('provides eight distinct cheque and note list, issue and endorsement layouts without bypassing posting services', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/finance/securities-center.blade.php'));
    $route = file_get_contents(base_path('routes/web.php'));
    foreach ([
        'finance.checks.index', 'finance.checks.incoming', 'finance.checks.outgoing', 'finance.checks.endorsement',
        'finance.notes.index', 'finance.notes.incoming', 'finance.notes.outgoing', 'finance.notes.endorsement',
    ] as $name) {
        expect($route)->toContain("->name('".$name."')");
    }
    foreach ([
        'Çek Numarası *', 'Senet Numarası *', 'Düzenleme Tarihi *', 'Vade Tarihi *',
        'Çek Tutarı *', 'Senet Tutarı *', 'Muhatap Banka', 'Ödeme Yapılacak Banka Hesabı',
        'Borçlu / Lehtar', 'Ciro Edilecek', 'Ciro Tarihi *',
        'Ciro Edilen / Hedef Cari *', 'Ciro Açıklaması',
        'v65-ref-security-records', 'wire:click="create"', 'wire:click="postPayroll"',
    ] as $part) {
        expect($view)->toContain($part);
    }
    $action = file_get_contents(base_path('app/Actions/Finance/PostSecurityPayroll.php'));
    expect($view)->toContain('wire:model.blur="note"')
        ->and($action)->toContain("'notes' => \$note")
        ->toContain("MutationAuthorizer::authorize('security_payrolls.create')");
});
