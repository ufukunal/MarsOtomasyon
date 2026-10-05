<?php

namespace App\Support\Printing\Label;

use App\Support\DocumentTemplates\FrozenDocumentRenderData;
use DomainException;

final readonly class CartonLabelData
{
    public function __construct(
        public string $sourceType,
        public int $sourceId,
        public array $document,
        public array $shipping = [],
        public array $company = [],
        public array $user = [],
    ) {
        if (trim($this->sourceType) === '' || $this->sourceId < 1) {
            throw new DomainException('Koli etiketi ambar/sevk kaynağı olmadan oluşturulamaz.');
        }

        if ($this->document === []) {
            throw new DomainException('Koli etiketi kaynak belge snapshot verisi gerektirir.');
        }
    }

    public function toRenderData(): FrozenDocumentRenderData
    {
        return new FrozenDocumentRenderData([
            'document' => $this->document,
            'shipping' => $this->shipping,
            'company' => $this->company,
            'user' => $this->user,
        ]);
    }
}
