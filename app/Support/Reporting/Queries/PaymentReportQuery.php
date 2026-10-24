<?php

namespace App\Support\Reporting\Queries;

use App\Enums\DocumentType;

final class PaymentReportQuery extends AbstractDocumentReportQuery
{
    protected function key(): string
    {
        return 'finance.payments';
    }

    protected function title(): string
    {
        return 'Ödeme Belgeleri';
    }

    protected function category(): string
    {
        return 'Finans';
    }

    protected function permission(): string
    {
        return 'payments.view';
    }

    protected function documentTypes(): array
    {
        return [DocumentType::Payment->value];
    }

    protected function drillDownTarget(): string
    {
        return 'payments';
    }
}
