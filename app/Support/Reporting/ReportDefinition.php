<?php

namespace App\Support\Reporting;

use LogicException;

final readonly class ReportDefinition
{
    /**
     * @param  list<ReportFilterDefinition>  $filters
     * @param  list<ReportColumnDefinition>  $columns
     * @param  list<ReportSort>  $defaultSort
     * @param  list<ReportTotalDefinition>  $totals
     * @param  list<ReportDrillDownDefinition>  $drillDowns
     * @param  list<string>  $exporters
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $category,
        public string $permission,
        public array $filters,
        public array $columns,
        public array $defaultSort,
        public array $totals = [],
        public array $drillDowns = [],
        public array $exporters = ['screen', 'pdf', 'xlsx', 'csv'],
        public int $version = 1,
    ) {
        if ($this->key === '' || $this->title === '' || $this->category === '' || $this->permission === '') {
            throw new LogicException('Rapor definition key/title/category/permission boş olamaz.');
        }

        $this->assertUniqueKeys($this->filters, 'filter');
        $this->assertUniqueKeys($this->columns, 'column');
        $this->assertUniqueKeys($this->totals, 'total');
        $this->assertUniqueKeys($this->drillDowns, 'drill-down');

        $columns = $this->columnMap();

        foreach ($this->defaultSort as $sort) {
            if (! isset($columns[$sort->key]) || ! $columns[$sort->key]->sortable) {
                throw new LogicException("Rapor default sort kolonu geçersiz: {$sort->key}.");
            }
        }

        foreach ($this->exporters as $exporter) {
            if (! in_array($exporter, ['screen', 'pdf', 'xlsx', 'csv'], true)) {
                throw new LogicException("Rapor exporter geçersiz: {$exporter}.");
            }
        }
    }

    /** @return array<string,ReportFilterDefinition> */
    public function filterMap(): array
    {
        $result = [];

        foreach ($this->filters as $filter) {
            $result[$filter->key] = $filter;
        }

        return $result;
    }

    /** @return array<string,ReportColumnDefinition> */
    public function columnMap(): array
    {
        $result = [];

        foreach ($this->columns as $column) {
            $result[$column->key] = $column;
        }

        return $result;
    }

    /** @return array<string,ReportTotalDefinition> */
    public function totalMap(): array
    {
        $result = [];

        foreach ($this->totals as $total) {
            $result[$total->key] = $total;
        }

        return $result;
    }

    /** @return list<string> */
    public function defaultColumnKeys(bool $canViewCost): array
    {
        return array_values(array_map(
            fn (ReportColumnDefinition $column): string => $column->key,
            array_filter(
                $this->columns,
                fn (ReportColumnDefinition $column): bool => $column->defaultVisible
                    && ($canViewCost || ! $column->costSensitive),
            ),
        ));
    }

    /** @param list<ReportFilterDefinition|ReportColumnDefinition|ReportTotalDefinition|ReportDrillDownDefinition> $items */
    private function assertUniqueKeys(array $items, string $label): void
    {
        $keys = [];

        foreach ($items as $item) {
            if (! isset($item->key) || trim((string) $item->key) === '') {
                throw new LogicException("Rapor {$label} key boş olamaz.");
            }

            if (isset($keys[$item->key])) {
                throw new LogicException("Rapor {$label} key tekrarlı: {$item->key}.");
            }

            $keys[$item->key] = true;
        }
    }
}
