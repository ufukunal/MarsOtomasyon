<?php

namespace App\Support\Reporting;

use App\Models\User;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final class ReportEngine
{
    public function __construct(private readonly ReportRegistry $registry) {}

    public function run(
        string $key,
        ReportRequest $request,
        ?User $actor = null,
    ): ReportResult {
        PeriodContext::ensure();

        $actor ??= auth()->user();

        if (! $actor) {
            throw new AuthorizationException('Rapor çalıştırmak için kullanıcı bağlamı gereklidir.');
        }

        $query = $this->registry->query($key);
        $definition = $query->definition();
        $gate = Gate::forUser($actor);
        $gate->authorize($definition->permission);

        $canViewCost = $gate->allows('cost.view');
        $filters = $this->normalizeFilters($definition, $request->filters);
        $columns = $this->normalizeColumns($definition, $request->columns, $canViewCost);
        $sort = $this->normalizeSort($definition, $request->sort, $canViewCost);
        $totalKeys = array_values(array_map(
            fn (ReportTotalDefinition $total): string => $total->key,
            array_filter(
                $definition->totals,
                fn (ReportTotalDefinition $total): bool => $canViewCost || ! $total->costSensitive,
            ),
        ));

        $maxPageSize = max(1, (int) config('reporting.max_page_size', 250));

        if ($request->limit < 1 || $request->limit > $maxPageSize || $request->offset < 0) {
            throw new DomainException('Rapor pagination parametreleri geçersiz.');
        }

        $context = new ReportExecutionContext(
            filters: $filters,
            columns: $columns,
            sort: $sort,
            totalKeys: $totalKeys,
            limit: $request->limit,
            offset: $request->offset,
            canViewCost: $canViewCost,
        );
        $data = $query->execute($context);
        $columnMap = $definition->columnMap();
        $visibleColumns = array_map(
            fn (string $column): ReportColumnDefinition => $columnMap[$column],
            $columns,
        );
        $drillDowns = array_values(array_filter(
            $definition->drillDowns,
            fn (ReportDrillDownDefinition $drillDown): bool => $gate->allows($drillDown->permission),
        ));

        return new ReportResult(
            key: $definition->key,
            title: $definition->title,
            category: $definition->category,
            columns: $visibleColumns,
            drillDowns: $drillDowns,
            rows: $data->rows,
            totals: $data->totals,
            totalRows: $data->totalRows,
            filters: $filters,
            sort: $sort,
            limit: $request->limit,
            offset: $request->offset,
            definitionVersion: $definition->version,
        );
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    private function normalizeFilters(ReportDefinition $definition, array $input): array
    {
        $filterMap = $definition->filterMap();
        $unknown = array_values(array_diff(array_keys($input), array_keys($filterMap)));

        if ($unknown !== []) {
            throw new DomainException('Desteklenmeyen rapor filtresi: '.implode(', ', $unknown).'.');
        }

        $normalized = [];

        foreach ($filterMap as $key => $filter) {
            $value = array_key_exists($key, $input) ? $input[$key] : null;
            $value = $filter->normalize($value);

            if ($value !== null) {
                $normalized[$key] = $value;
            }
        }

        if (isset($normalized['date_from'], $normalized['date_to'])
            && $normalized['date_from'] > $normalized['date_to']) {
            throw new DomainException('Rapor başlangıç tarihi bitiş tarihinden büyük olamaz.');
        }

        return $normalized;
    }

    /**
     * @param  list<string>|null  $requested
     * @return list<string>
     */
    private function normalizeColumns(
        ReportDefinition $definition,
        ?array $requested,
        bool $canViewCost,
    ): array {
        $columnMap = $definition->columnMap();
        $columns = $requested ?? $definition->defaultColumnKeys($canViewCost);

        if ($columns === []) {
            throw new DomainException('Rapor en az bir kolon içermelidir.');
        }

        $normalized = [];

        foreach ($columns as $column) {
            if (! isset($columnMap[$column])) {
                throw new DomainException('Desteklenmeyen rapor kolonu.');
            }

            if ($columnMap[$column]->costSensitive && ! $canViewCost) {
                throw new AuthorizationException('Maliyet/kâr rapor kolonu cost.view izni gerektirir.');
            }

            if (! in_array($column, $normalized, true)) {
                $normalized[] = $column;
            }
        }

        return $normalized;
    }

    /**
     * @param  list<array{key:string,direction?:string}|ReportSort>  $requested
     * @return list<ReportSort>
     */
    private function normalizeSort(
        ReportDefinition $definition,
        array $requested,
        bool $canViewCost,
    ): array {
        $columnMap = $definition->columnMap();
        $source = $requested === [] ? $definition->defaultSort : $requested;
        $sort = [];
        $seen = [];

        foreach ($source as $item) {
            $candidate = $item instanceof ReportSort
                ? $item
                : new ReportSort($item['key'], strtolower((string) ($item['direction'] ?? 'asc')));

            if (! isset($columnMap[$candidate->key]) || ! $columnMap[$candidate->key]->sortable) {
                throw new DomainException("Desteklenmeyen rapor sıralama kolonu: {$candidate->key}.");
            }

            if ($columnMap[$candidate->key]->costSensitive && ! $canViewCost) {
                throw new AuthorizationException('Maliyet/kâr sıralaması cost.view izni gerektirir.');
            }

            if (! isset($seen[$candidate->key])) {
                $sort[] = $candidate;
                $seen[$candidate->key] = true;
            }
        }

        return $sort;
    }
}
