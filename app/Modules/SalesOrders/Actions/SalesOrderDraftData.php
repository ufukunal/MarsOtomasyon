<?php

namespace App\Modules\SalesOrders\Actions;

final readonly class SalesOrderDraftData
{
    /** @param list<SalesOrderLineData> $lines */
    public function __construct(
        public int $accountId,
        public string $orderDate,
        public string $currencyCode,
        public string $documentDiscountRate,
        public ?string $note,
        public array $lines,
    ) {}
}
