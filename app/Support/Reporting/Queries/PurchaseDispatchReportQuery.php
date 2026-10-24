<?php

namespace App\Support\Reporting\Queries;

use App\Enums\DocumentType;

/**
 * Uses the shared document reporting engine: permission checks, filters,
 * currency-separated totals, pagination, and optional document navigation.
 */
final class PurchaseDispatchReportQuery extends AbstractDocumentReportQuery
{
    protected function key(): string
    {
        return 'purchases.dispatches';
    }

    protected function title(): string
    {
        return 'Alış İrsaliyeleri';
    }

    protected function category(): string
    {
        return 'Alış';
    }

    protected function permission(): string
    {
        return 'purchase_dispatches.view';
    }

    protected function documentTypes(): array
    {
        return [DocumentType::PurchaseDispatch->value];
    }

    protected function drillDownTarget(): string
    {
        return 'purchase_dispatches';
    }
}
