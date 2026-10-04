<?php

namespace App\DataTransfer;

final readonly class CopyResult
{
    /**
     * @param  list<array<string, mixed>>  $copied
     * @param  list<array<string, mixed>>  $existing
     * @param  list<CopyConflict|array<string, mixed>>  $conflicts
     * @param  list<string>  $warnings
     * @param  list<int>  $cancelled
     */
    public function __construct(
        public array $copied = [],
        public array $existing = [],
        public array $conflicts = [],
        public array $warnings = [],
        public array $cancelled = [],
    ) {}

    /** @return array<string, mixed> */
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
