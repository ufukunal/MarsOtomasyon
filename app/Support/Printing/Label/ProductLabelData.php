<?php

namespace App\Support\Printing\Label;

use App\Support\DocumentTemplates\FrozenDocumentRenderData;

final readonly class ProductLabelData
{
    /**
     * @param  array<string,mixed>  $company
     * @param  array<string,mixed>  $user
     */
    public function __construct(
        public int $productId,
        public string $code,
        public string $name,
        public ?string $barcode = null,
        public ?string $price = null,
        public ?string $unit = null,
        public array $company = [],
        public array $user = [],
    ) {}

    public function toRenderData(): FrozenDocumentRenderData
    {
        return new FrozenDocumentRenderData([
            'product' => [
                'id' => $this->productId,
                'code' => $this->code,
                'name' => $this->name,
                'barcode' => $this->barcode,
                'price' => $this->price,
                'unit' => $this->unit,
            ],
            'company' => $this->company,
            'user' => $this->user,
        ]);
    }
}
