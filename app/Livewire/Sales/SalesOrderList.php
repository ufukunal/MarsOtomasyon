<?php

namespace App\Livewire\Sales;

use App\Enums\DocumentType;

class SalesOrderList extends BaseSalesDocumentList
{
    protected function documentType(): DocumentType { return DocumentType::SalesOrder; }
    protected function permission(): string { return 'sales_orders.view'; }
    protected function pageTitle(): string { return 'Satış Siparişleri'; }
    protected function editRoute(): string { return 'sales.orders.edit'; }
}
