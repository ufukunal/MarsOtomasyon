<?php

namespace App\Support\Reporting;

final readonly class ReportQueryResult
{
    /**
     * @param  list<array<string,mixed>>  $rows
     * @param  array<string,mixed>  $totals
     */
    public function __construct(
        public array $rows,
        public array $totals,
        public int $totalRows,
    ) {}
}
