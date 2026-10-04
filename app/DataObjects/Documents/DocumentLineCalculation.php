<?php

namespace App\DataObjects\Documents;

final readonly class DocumentLineCalculation
{
    public function __construct(
        public string $gross,
        public string $discountRate,
        public string $discountAmount,
        public string $lineTotal,
        public string $vatRate,
    ) {}
}
