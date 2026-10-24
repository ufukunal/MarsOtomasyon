<?php

namespace App\Support\Reporting\Queries;

use App\Enums\DocumentType;

/**
 * Uses the shared document reporting engine: permission checks, filters,
 * currency-separated totals, pagination, and optional document navigation.
 */
final class GoodsReceiptReportQuery extends AbstractDocumentReportQuery
{
    protected function key(): string
    {
        return 'purchases.goods_receipts';
    }

    protected function title(): string
    {
        return 'Mal Kabul Fişleri';
    }

    protected function category(): string
    {
        return 'Alış';
    }

    protected function permission(): string
    {
        return 'goods_receipts.view';
    }

    protected function documentTypes(): array
    {
        return [DocumentType::GoodsReceipt->value];
    }

    protected function drillDownTarget(): string
    {
        return 'goods_receipts';
    }
}
