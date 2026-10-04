<?php

namespace App\DataObjects;

final readonly class ReservationResult
{
    /** @param list<int> $reservationIds */
    public function __construct(
        public array $reservationIds,
        public string $reservedQuantity,
        public string $openQuantity,
    ) {}
}
