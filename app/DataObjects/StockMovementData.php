<?php

namespace App\DataObjects;

final readonly class StockMovementData
{
    public function __construct(
        public int $productId,
        public int $locationId,
        public string $movementDate,
        public string $direction,
        public string $reason,
        public string $quantity,
        public ?string $unitCost = null,
        public bool $updatesAverage = false,
        public ?string $documentType = null,
        public ?int $documentId = null,
        public ?string $documentNo = null,
        public ?string $note = null,
        public ?int $actorUserId = null,
        public ?string $actorUserName = null,
    ) {}
}
