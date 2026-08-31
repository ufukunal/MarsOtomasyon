<?php

namespace App\Modules\Products\Actions;

final readonly class CreateProductData
{
    /**
     * @param  list<string>  $additionalBarcodes
     */
    public function __construct(
        public string $code,
        public string $name,
        public ?int $categoryId,
        public int $unitId,
        public int $taxId,
        public string $salePriceNet = '0',
        public string $purchasePriceNet = '0',
        public ?string $primaryBarcode = null,
        public array $additionalBarcodes = [],
        public ?string $brand = null,
    ) {}
}
