<?php

it('separates reservation and fulfillment controls with labelled quantities and locations', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($view)->toContain('v65-order-fulfillment-lines')
        ->toContain('Sevk / Fatura Miktarı')
        ->toContain('Sevk / Fatura Deposu')
        ->toContain('Rezervasyon Deposu')
        ->toContain('Aktif rezervasyon (temel birim)')
        ->toContain('wire:click="reserve"')
        ->toContain('@can(\'reservations.create\')')
        ->toContain('wire:confirm=')
        ->toContain('role="status"')
        ->and($css)->toContain('.v65-order-fulfillment-inputs')
        ->toContain('.v65-reservation-notice');
});

it('reports no location, no stock and successful reservation as distinct results', function (): void {
    $source = file_get_contents(base_path('app/Livewire/Sales/SalesOrderEditor.php'));
    expect($source)->toContain('Rezervasyon için en az bir stok satırında rezervasyon deposu seçin.')
        ->toContain('Seçilen depolarda kullanılabilir stok bulunamadı.')
        ->toContain('temel birim stok rezerve edildi.')
        ->toContain("where('status', 'active')")
        ->toContain("where('document_type', DocumentType::SalesOrder->value)")
        ->toContain("where('document_id', \$this->document->id)");
});
