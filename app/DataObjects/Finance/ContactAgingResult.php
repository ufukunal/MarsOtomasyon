<?php

namespace App\DataObjects\Finance;

final readonly class ContactAgingResult
{
    /**
     * @param list<AgingLine> $lines
     * @param array<string,string> $buckets
     */
    public function __construct(
        public array $lines,
        public string $balance,
        public string $openDebit,
        public string $excessCredit,
        public array $buckets,
    ) {}
}
