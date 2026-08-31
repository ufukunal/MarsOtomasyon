<?php

namespace App\Modules\Products\Actions;

use App\Modules\Products\Enums\ProductStatus;

final readonly class UpdateProductData
{
    /** @param list<string> $additionalBarcodes */
    public function __construct(
        public string $code,
        public ProductStatus $status,
        public string $name,
        public ?int $categoryId,
        public int $unitId,
        public int $taxId,
        public string $salePriceNet,
        public string $purchasePriceNet,
        public ?string $primaryBarcode = null,
        public array $additionalBarcodes = [],
        public ?string $brand = null,
    ) {}
}
