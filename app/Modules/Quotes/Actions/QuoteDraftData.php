<?php

namespace App\Modules\Quotes\Actions;

final readonly class QuoteDraftData
{
    /** @param list<QuoteLineData> $lines */
    public function __construct(
        public int $accountId,
        public string $quoteDate,
        public ?string $validUntil,
        public string $currencyCode,
        public string $documentDiscountRate,
        public ?string $note,
        public array $lines,
    ) {}
}
