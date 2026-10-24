<?php

namespace App\Support\Reporting\Queries;

use App\Enums\DocumentType;

/**
 * Uses the shared document reporting engine: permission checks, filters,
 * currency-separated totals, pagination, and optional document navigation.
 */
final class PurchaseQuoteReportQuery extends AbstractDocumentReportQuery
{
    protected function key(): string
    {
        return 'purchases.quotes';
    }

    protected function title(): string
    {
        return 'Alış Teklifleri';
    }

    protected function category(): string
    {
        return 'Alış';
    }

    protected function permission(): string
    {
        return 'purchase_quotes.view';
    }

    protected function documentTypes(): array
    {
        return [DocumentType::PurchaseQuote->value];
    }

    protected function drillDownTarget(): string
    {
        return 'purchase_quotes';
    }
}
