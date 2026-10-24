<?php

namespace App\Support\Reporting\Queries;

use App\Enums\DocumentType;

/**
 * Uses the shared document reporting engine: permission checks, filters,
 * currency-separated totals, pagination, and optional document navigation.
 */
final class PurchaseOrderReportQuery extends AbstractDocumentReportQuery
{
    protected function key(): string
    {
        return 'purchases.orders';
    }

    protected function title(): string
    {
        return 'Alış Siparişleri';
    }

    protected function category(): string
    {
        return 'Alış';
    }

    protected function permission(): string
    {
        return 'purchase_orders.view';
    }

    protected function documentTypes(): array
    {
        return [DocumentType::PurchaseOrder->value];
    }

    protected function drillDownTarget(): string
    {
        return 'purchase_orders';
    }
}
