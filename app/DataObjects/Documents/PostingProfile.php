<?php

namespace App\DataObjects\Documents;

final readonly class PostingProfile
{
    public function __construct(
        public string $calculationMode,
        public bool $stockOut,
        public bool $consumeReservations,
        public ?string $contactDirection,
        public bool $financialIn,
        public bool $stockIn = false,
        public bool $financialOut = false,
    ) {}
}
