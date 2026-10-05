<?php

namespace App\Support\Reporting\Queries;

use App\Enums\DocumentType;

final class ReturnDocumentReportQuery extends AbstractDocumentReportQuery
{
    protected function key(): string
    {
        return 'returns.documents';
    }

    protected function title(): string
    {
        return 'İade Dökümü';
    }

    protected function category(): string
    {
        return 'İade / Karantina';
    }

    protected function permission(): string
    {
        return 'returns.view';
    }

    protected function documentTypes(): array
    {
        return [
            DocumentType::SalesReturn->value,
            DocumentType::PurchaseReturn->value,
        ];
    }

    protected function drillDownTarget(): string
    {
        return 'returns';
    }
}
