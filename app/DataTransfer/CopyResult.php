<?php

namespace App\DataTransfer;

final readonly class CopyResult
{
    public function __construct(
        public array $copied = [],
        public array $existing = [],
        public array $conflicts = [],
        public array $warnings = [],
        public array $cancelled = [],
    ) {}

    public function toArray(): array
    {
        return [
            'copied' => $this->copied,
            'existing' => $this->existing,
            'conflicts' => array_map(
                fn ($conflict) => $conflict instanceof CopyConflict ? $conflict->toArray() : $conflict,
                $this->conflicts,
            ),
            'warnings' => $this->warnings,
            'cancelled' => $this->cancelled,
        ];
    }
}
