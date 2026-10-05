<?php

namespace App\Support\Reporting;

final readonly class ReportCatalogItem
{
    /**
     * @param list<ReportFilterDefinition> $filters
     * @param list<ReportColumnDefinition> $columns
     * @param list<ReportSort> $defaultSort
     * @param list<ReportTotalDefinition> $totals
     * @param list<ReportDrillDownDefinition> $drillDowns
     * @param list<string> $exporters
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $category,
        public array $filters,
        public array $columns,
        public array $defaultSort,
        public array $totals,
        public array $drillDowns,
        public array $exporters,
        public int $version,
    ) {}
}
