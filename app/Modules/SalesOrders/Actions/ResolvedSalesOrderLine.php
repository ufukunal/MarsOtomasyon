<?php

namespace App\Modules\SalesOrders\Actions;

use App\Modules\Quotes\Pricing\TaxCalculationLineResult;

final readonly class ResolvedSalesOrderLine
{
    public function __construct(
        public int $position,
        public int $productId,
        public string $productCode,
        public string $productName,
        public string $description,
        public int $taxId,
        public string $taxCode,
        public ?int $taxZeroReasonId,
        public TaxCalculationLineResult $calculation,
    ) {}
}
