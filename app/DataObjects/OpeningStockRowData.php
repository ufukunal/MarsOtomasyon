<?php

namespace App\DataObjects;

final readonly class OpeningStockRowData
{
    public function __construct(
        public string $productCode,
        public string $locationCode,
        public string $quantity,
        public string $unitCost,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            productCode: strtoupper(trim((string) ($row['product_code'] ?? ''))),
            locationCode: strtoupper(trim((string) ($row['location_code'] ?? ''))),
            quantity: trim((string) ($row['quantity'] ?? '')),
            unitCost: trim((string) ($row['unit_cost'] ?? '')),
        );
    }
}
