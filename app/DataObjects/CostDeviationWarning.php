<?php

namespace App\DataObjects;

final readonly class CostDeviationWarning
{
    public function __construct(
        public string $deviationPercent,
        public string $message,
    ) {}
}
