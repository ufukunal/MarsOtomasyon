<?php

namespace App\Modules\PurchaseReturns\Actions;

final readonly class PurchaseReturnDraftData
{
    /** @param list<PurchaseReturnLineData> $lines */
    public function __construct(
        public int $purchaseOrderId,
        public string $returnDate,
        public ?string $note,
        public array $lines,
    ) {}
}
