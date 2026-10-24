<?php

namespace App\Support\Reporting\Queries;

use App\Enums\DocumentType;

final class AdvanceReportQuery extends AbstractDocumentReportQuery
{
    protected function key(): string
    {
        return 'finance.advances';
    }

    protected function title(): string
    {
        return 'Avans ve Avans İadeleri';
    }

    protected function category(): string
    {
        return 'Finans';
    }

    protected function permission(): string
    {
        return 'advances.view';
    }

    protected function documentTypes(): array
    {
        return [DocumentType::Advance->value, DocumentType::AdvanceReturn->value];
    }

    protected function drillDownTarget(): string
    {
        return 'advances';
    }
}
