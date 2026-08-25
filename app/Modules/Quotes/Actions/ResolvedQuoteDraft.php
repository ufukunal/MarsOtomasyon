<?php

namespace App\Modules\Quotes\Actions;

use App\Modules\Quotes\Pricing\TaxCalculationResult;

final readonly class ResolvedQuoteDraft
{
    /** @param list<ResolvedQuoteLine> $lines */
    public function __construct(
        public int $accountId,
        public string $quoteDate,
        public ?string $validUntil,
        public string $currencyCode,
        public string $documentDiscountRate,
        public ?string $note,
        public array $lines,
        public TaxCalculationResult $calculation,
    ) {}
}
