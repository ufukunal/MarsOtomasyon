<?php

namespace App\DataObjects\Documents;

final readonly class DocumentTotals
{
    /** @param list<DocumentLineCalculation> $lines */
    public function __construct(
        public array $lines,
        public string $discountRate,
        public string $discountAmount,
        public string $subtotal,
        public string $taxBase,
        public string $vatAmount,
        public string $roundingDifference,
        public string $grandTotal,
    ) {}
}
