<?php

namespace App\Modules\GoodsReceipts\Actions;

final readonly class ResolvedGoodsReceiptDraft
{
    /** @param list<ResolvedGoodsReceiptLine> $lines */
    public function __construct(
        public int $purchaseOrderId,
        public int $accountId,
        public string $receiptDate,
        public ?string $note,
        public array $lines,
    ) {}
}
