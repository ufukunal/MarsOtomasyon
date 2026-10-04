<?php

namespace App\DataObjects\Sales;

final readonly class SalesRiskProjection
{
    public function __construct(
        public string $currentBalance,
        public string $orderTotal,
        public ?string $securityRisk,
        public bool $projectionComplete,
        public string $knownExposure,
        public string $riskLimit,
        public bool $knownLimitExceeded,
        public string $knownOverLimit,
    ) {}
}
