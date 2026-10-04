<?php

namespace App\DataObjects\Finance;

final readonly class AgingLine
{
    public function __construct(
        public int $transactionId,
        public string $transactionDate,
        public string $dueDate,
        public string $originalAmount,
        public string $appliedCredit,
        public string $remaining,
        public string $bucket,
        public string $color,
    ) {}
}
