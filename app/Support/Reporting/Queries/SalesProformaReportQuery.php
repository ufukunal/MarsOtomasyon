<?php

namespace App\Support\Reporting\Queries;

use App\Enums\DocumentType;

/**
 * Uses the shared document reporting engine: permission checks, filters,
 * currency-separated totals, pagination, and optional document navigation.
 */
final class SalesProformaReportQuery extends AbstractDocumentReportQuery
{
    protected function key(): string
    {
        return 'sales.proformas';
    }

    protected function title(): string
    {
        return 'Satış Proformaları';
    }

    protected function category(): string
    {
        return 'Satış';
    }

    protected function permission(): string
    {
        return 'proformas.view';
    }

    protected function documentTypes(): array
    {
        return [DocumentType::Proforma->value];
    }

    protected function drillDownTarget(): string
    {
        return 'sales_proformas';
    }
}
