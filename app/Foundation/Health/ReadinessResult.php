<?php

namespace App\Foundation\Health;

final readonly class ReadinessResult
{
    /** @param list<string> $failedDependencies */
    public function __construct(
        public bool $ready,
        public array $failedDependencies = [],
    ) {
    }
}
