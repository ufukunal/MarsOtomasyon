<?php

namespace App\Support\Reporting\Queries;

use App\Enums\DocumentType;

final class CollectionReportQuery extends AbstractDocumentReportQuery
{
    protected function key(): string
    {
        return 'finance.collections';
    }

    protected function title(): string
    {
        return 'Tahsilat Belgeleri';
    }

    protected function category(): string
    {
        return 'Finans';
    }

    protected function permission(): string
    {
        return 'collections.view';
    }

    protected function documentTypes(): array
    {
        return [DocumentType::Collection->value];
    }

    protected function drillDownTarget(): string
    {
        return 'collections';
    }
}
