<?php

namespace App\Livewire\Purchases;

use App\Enums\DocumentType;

class SupplierInvoiceList extends BasePurchaseDocumentList
{
    protected function documentType(): DocumentType { return DocumentType::SupplierInvoice; }
    protected function permission(): string { return 'supplier_invoices.view'; }
    protected function pageTitle(): string { return 'Alış Faturaları'; }
    protected function editRoute(): string { return 'purchases.invoices.edit'; }
}
