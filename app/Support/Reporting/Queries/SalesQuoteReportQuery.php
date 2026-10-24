<?php

namespace App\Support\Reporting\Queries;

use App\Enums\DocumentType;

/**
 * Uses the shared document reporting engine: permission checks, filters,
 * currency-separated totals, pagination, and optional document navigation.
 */
final class SalesQuoteReportQuery extends AbstractDocumentReportQuery
{
    protected function key(): string
    {
        return 'sales.quotes';
    }

    protected function title(): string
    {
        return 'Satış Teklifleri';
    }

    protected function category(): string
    {
        return 'Satış';
    }

    protected function permission(): string
    {
        return 'quotes.view';
    }

    protected function documentTypes(): array
    {
        return [DocumentType::Quote->value];
    }

    protected function drillDownTarget(): string
    {
        return 'sales_quotes';
    }
}
