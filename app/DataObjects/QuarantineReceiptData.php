<?php

namespace App\DataObjects;

final readonly class QuarantineReceiptData
{
    public function __construct(
        public int $productId,
        public int $locationId,
        public string $movementDate,
        public string $quantity,
        public string $unitCost,
        public ?string $sourceDocumentType = null,
        public ?int $sourceDocumentId = null,
        public ?int $sourceLineId = null,
        public ?string $documentNo = null,
        public ?string $note = null,
        public ?int $actorUserId = null,
        public ?string $actorUserName = null,
    ) {}
}
