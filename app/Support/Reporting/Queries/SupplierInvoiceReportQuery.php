<?php

namespace App\Support\Reporting\Queries;

use App\Enums\DocumentType;

final class SupplierInvoiceReportQuery extends AbstractDocumentReportQuery
{
    protected function key(): string
    {
        return 'purchases.supplier_invoices';
    }

    protected function title(): string
    {
        return 'Alış Dökümü';
    }

    protected function category(): string
    {
        return 'Alış';
    }

    protected function permission(): string
    {
        return 'supplier_invoices.view';
    }

    protected function documentTypes(): array
    {
        return [DocumentType::SupplierInvoice->value];
    }

    protected function drillDownTarget(): string
    {
        return 'supplier_invoices';
    }
}
