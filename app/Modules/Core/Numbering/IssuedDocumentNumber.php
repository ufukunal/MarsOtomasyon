<?php

namespace App\Modules\Core\Numbering;

use App\Modules\Core\Enums\DocumentType;

final readonly class IssuedDocumentNumber
{
    public function __construct(
        public DocumentType $documentType,
        public string $seriesCode,
        public int $sequenceValue,
        public string $number,
    ) {}
}
