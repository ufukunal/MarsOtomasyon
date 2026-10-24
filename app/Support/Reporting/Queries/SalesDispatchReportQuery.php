<?php

namespace App\Support\Reporting\Queries;

use App\Enums\DocumentType;

/**
 * Uses the shared document reporting engine: permission checks, filters,
 * currency-separated totals, pagination, and optional document navigation.
 */
final class SalesDispatchReportQuery extends AbstractDocumentReportQuery
{
    protected function key(): string
    {
        return 'sales.dispatches';
    }

    protected function title(): string
    {
        return 'Satış İrsaliyeleri';
    }

    protected function category(): string
    {
        return 'Satış';
    }

    protected function permission(): string
    {
        return 'dispatches.view';
    }

    protected function documentTypes(): array
    {
        return [DocumentType::Dispatch->value];
    }

    protected function drillDownTarget(): string
    {
        return 'sales_dispatches';
    }
}
