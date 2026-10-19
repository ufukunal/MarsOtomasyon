<?php

it('stores third-party delivery snapshots in the sales invoice and validates the chosen address owner', function (): void {
    $editor = file_get_contents(base_path('app/Livewire/Sales/SalesInvoiceEditor.php'));
    $base = file_get_contents(base_path('app/Livewire/Sales/BaseSalesDocumentEditor.php'));
    $view = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));

    expect($editor)->toContain('public function updatedUiMetaDeliveryMode(): void')
        ->toContain('public function updatedUiMetaDeliveryAddressId(): void')
        ->toContain('public function invoiceDeliveryAddresses():')
        ->toContain("'type', 'shipping'")
        ->toContain("->where('contact_id', \$this->contactId)")
        ->toContain("['customer', 'saved', 'manual']")
        ->toContain('private function prepareInvoiceDelivery(): void')
        ->toContain("['delivery_company']")
        ->toContain('parent::save($action)')
        ->and($base)->toContain("attribute === 'uiMeta.delivery_address'")
        ->toContain("'ui_meta' => \$this->uiMeta")
        ->and($view)->toContain('wire:model.live="uiMeta.delivery_mode"')
        ->toContain('wire:model.live="uiMeta.delivery_address_id"')
        ->toContain('wire:model.blur="uiMeta.delivery_company"')
        ->toContain('wire:model.blur="uiMeta.delivery_address"')
        ->toContain('Cari kartında sevk adreslerini yönet')
        ->toContain('Teslim Edilecek Firma')
        ->toContain("{{ \$uiMeta['delivery_company'] }}");
});

it('prefills but does not automatically save warehouse paperwork from a posted invoice', function (): void {
    $editor = file_get_contents(base_path('app/Livewire/Pages/Warehouse/TransportSlipEditor.php'));
    $view = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $warehouse = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-editor.blade.php'));
    $printing = file_get_contents(base_path('resources/views/printing/transport-slip.blade.php'));

    expect($editor)->toContain('#[Locked]')
        ->toContain('public ?int $sourceInvoiceId = null')
        ->toContain('private function prefillFromInvoice(int $id): void')
        ->toContain("->where('document_type', DocumentType::SalesInvoice)")
        ->toContain("->where('status', 'posted')")
        ->toContain("'delivery_company'")
        ->toContain("'delivery_address'")
        ->toContain("'product_id'")
        ->toContain("'quantity'")
        ->toContain("'incomingRelations.sourceDocument'")
        ->toContain("(int) \$invoice->contact_id === \$this->contactId")
        ->and($view)->toContain("route('warehouse.transport.create', ['invoice' => \$document->id])")
        ->toContain('Ambar Fişi Oluştur')
        ->toContain("can('warehouse_slips.view')")
        ->and($warehouse)->toContain('$sourceInvoiceId !== null')
        ->toContain('Koli adedi, taşıyıcı ve teslimat bilgilerini kontrol')
        ->and($editor)->toContain("($this->slip?->number ?? '')")
        ->and($printing)->toContain('$slip->delivery_company')
        ->toContain('$slip->delivery_address')
        ->toContain('$slip->package_count')
        ->toContain('OpaqueQrSvg::token');
});
