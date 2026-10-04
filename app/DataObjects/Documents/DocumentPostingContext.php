<?php

namespace App\DataObjects\Documents;

final readonly class DocumentPostingContext
{
    public function __construct(
        public ?string $contactDirection = null,
        public ?string $accountType = null,
        public ?int $accountId = null,
        public ?string $reason = null,
        public ?string $contactAmount = null,
        public ?string $contactCurrency = null,
    ) {}
}
