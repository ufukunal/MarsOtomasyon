<?php

it('derives approved cari monthly metrics only from real posted sales, orders and collections', function (): void {
    $class = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactDetail.php'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));

    expect($class)->toContain("where('document_type', DocumentType::SalesOrder->value)")
        ->toContain("where('d.document_type', DocumentType::Collection->value)")
        ->toContain("where('d.status', 'posted')")
        ->toContain("where('ct.currency', 'TRY')")
        ->toContain("where('ct.direction', 'credit')")
        ->toContain("'orders' => (int)")
        ->toContain("'collection_total' => (string)")
        ->and($view)->toContain('Sipariş Sayısı')
        ->toContain('Tahsilat (TRY)')
        ->not->toContain('557.550,00');
});

it('reports filtered statement opening and final balances separately by currency', function (): void {
    $class = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactDetail.php'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));

    expect($class)->toContain("\$total->opening = \$opening")
        ->toContain("\$total->closing = bcsub(")
        ->toContain("\$this->openingBalances()")
        ->and($view)->toContain('Devir Bakiye')
        ->toContain('Son Bakiye');
});
