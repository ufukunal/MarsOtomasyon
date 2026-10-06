<?php

namespace App\Support\Reporting\MultiPeriod;

use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportSort;

final readonly class ConsolidatedReportResult
{
    /**
     * @param  list<ReportColumnDefinition>  $columns
     * @param  list<array<string,mixed>>  $rows
     * @param  array<string,mixed>  $totals
     * @param  array<string,mixed>  $filters
     * @param  list<ReportSort>  $sort
     * @param  list<array{id:int,year:int,status:string}>  $periods
     */
    public function __construct(
        public string $key,
        public string $title,
        public array $columns,
        public array $rows,
        public array $totals,
        public int $totalRows,
        public array $filters,
        public array $sort,
        public array $periods,
        public int $definitionVersion,
        public int $limit,
        public int $offset,
    ) {}
}
