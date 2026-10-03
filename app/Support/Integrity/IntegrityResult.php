<?php

namespace App\Support\Integrity;

final readonly class IntegrityResult
{
    /**
     * @param array<int, array<string, mixed>> $mismatches
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public int $checked,
        public array $mismatches,
        public int $durationMs,
        public array $meta = [],
    ) {
    }

    public function mismatchCount(): int
    {
        return count($this->mismatches);
    }
}
