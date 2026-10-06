<?php

namespace App\Support\Reporting;

final readonly class ReportResult
{
    /**
     * @param  list<ReportColumnDefinition>  $columns
     * @param  list<ReportDrillDownDefinition>  $drillDowns
     * @param  list<array<string,mixed>>  $rows
     * @param  array<string,mixed>  $totals
     * @param  array<string,mixed>  $filters
     * @param  list<ReportSort>  $sort
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $category,
        public array $columns,
        public array $drillDowns,
        public array $rows,
        public array $totals,
        public int $totalRows,
        public array $filters,
        public array $sort,
        public int $limit,
        public int $offset,
        public int $definitionVersion,
    ) {}
}
