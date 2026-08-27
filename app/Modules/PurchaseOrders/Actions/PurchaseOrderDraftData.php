<?php

namespace App\Modules\PurchaseOrders\Actions;

final readonly class PurchaseOrderDraftData
{
    /** @param list<PurchaseOrderLineData> $lines */
    public function __construct(
        public int $accountId,
        public string $orderDate,
        public string $currencyCode,
        public string $documentDiscountRate,
        public ?string $note,
        public array $lines,
    ) {}
}
