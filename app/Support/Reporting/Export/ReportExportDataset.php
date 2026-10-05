<?php

namespace App\Support\Reporting\Export;

use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportSort;

final readonly class ReportExportDataset
{
    /**
     * @param list<ReportColumnDefinition> $columns
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $totals
     * @param array<string,mixed> $filters
     * @param list<ReportSort> $sort
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
        public int $definitionVersion,
    ) {}
}
