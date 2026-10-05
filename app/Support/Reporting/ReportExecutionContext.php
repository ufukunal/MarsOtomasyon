<?php

namespace App\Support\Reporting;

final readonly class ReportExecutionContext
{
    /**
     * @param array<string,mixed> $filters
     * @param list<string> $columns
     * @param list<ReportSort> $sort
     * @param list<string> $totalKeys
     */
    public function __construct(
        public array $filters,
        public array $columns,
        public array $sort,
        public array $totalKeys,
        public int $limit,
        public int $offset,
        public bool $canViewCost,
    ) {}

    public function hasColumn(string $key): bool
    {
        return in_array($key, $this->columns, true);
    }

    public function hasTotal(string $key): bool
    {
        return in_array($key, $this->totalKeys, true);
    }
}
