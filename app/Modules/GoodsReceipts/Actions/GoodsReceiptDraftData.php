<?php

namespace App\Modules\GoodsReceipts\Actions;

final readonly class GoodsReceiptDraftData
{
    /** @param list<GoodsReceiptLineData> $lines */
    public function __construct(
        public int $purchaseOrderId,
        public string $receiptDate,
        public ?string $note,
        public array $lines,
    ) {}
}
