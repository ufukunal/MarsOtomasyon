<?php

namespace App\Support\Reporting\Queries;

use App\Enums\DocumentType;

/**
 * Uses the shared document reporting engine: permission checks, filters,
 * currency-separated totals, pagination, and optional document navigation.
 */
final class SalesOrderReportQuery extends AbstractDocumentReportQuery
{
    protected function key(): string
    {
        return 'sales.orders';
    }

    protected function title(): string
    {
        return 'Satış Siparişleri';
    }

    protected function category(): string
    {
        return 'Satış';
    }

    protected function permission(): string
    {
        return 'sales_orders.view';
    }

    protected function documentTypes(): array
    {
        return [DocumentType::SalesOrder->value];
    }

    protected function drillDownTarget(): string
    {
        return 'sales_orders';
    }
}
