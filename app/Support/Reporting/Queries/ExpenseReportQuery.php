<?php

namespace App\Support\Reporting\Queries;

use App\Enums\DocumentType;

final class ExpenseReportQuery extends AbstractDocumentReportQuery
{
    protected function key(): string
    {
        return 'finance.expenses';
    }

    protected function title(): string
    {
        return 'Masraf Belgeleri';
    }

    protected function category(): string
    {
        return 'Finans';
    }

    protected function permission(): string
    {
        return 'expenses.view';
    }

    protected function documentTypes(): array
    {
        return [DocumentType::Expense->value];
    }

    protected function drillDownTarget(): string
    {
        return 'expenses';
    }
}
