<?php

namespace App\Support\Documents;

final readonly class DocumentDateValidationResult
{
    public function __construct(
        public bool $futureDate,
        public ?string $warning = null,
    ) {}
}
