<?php

namespace App\Livewire\Sales;

use App\Enums\DocumentType;

class SalesInvoiceList extends BaseSalesDocumentList
{
    protected function documentType(): DocumentType
    {
        return DocumentType::SalesInvoice;
    }

    protected function permission(): string
    {
        return 'sales_invoices.view';
    }

    protected function pageTitle(): string
    {
        return 'Satış Faturaları';
    }

    protected function editRoute(): string
    {
        return 'sales.invoices.edit';
    }
}
