<?php

namespace App\Support\Reporting\MultiPeriod;

use App\Actions\Reporting\RunReport;
use App\Models\User;
use App\Support\Period\PeriodContext;
use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportRegistry;
use App\Support\Reporting\ReportRequest;
use App\Support\Reporting\ReportResult;
use App\Support\Reporting\ReportSort;
use App\Support\Reporting\ReportTotalDefinition;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final class MultiPeriodQuery
{
    public function __construct(
        private readonly RunReport $runReport,
        private readonly ReportRegistry $registry,
        private readonly PeriodRangeSelector $periods,
    ) {}

    /** @param list<int|string> $periodIds */
    public function run(
        string $reportKey,
        array $periodIds,
        ReportRequest $request,
        ?User $actor = null,
        ?int $companyId = null,
    ): ConsolidatedReportResult {
        $originalCompanyId = PeriodContext::companyId();
        $originalPeriodId = PeriodContext::periodId();

        if ($companyId === null) {
            PeriodContext::ensure();
            $companyId = PeriodContext::companyId();
        }

        $actor ??= auth()->user();

        if (! $actor || ! $actor->is_active) {
            throw new AuthorizationException('Çok dönemli rapor için aktif kullanıcı gereklidir.');
        }

        if (! $companyId || $companyId < 1) {
            throw new DomainException('Çok dönemli rapor geçerli şirket bağlamı gerektirir.');
        }

        if ($originalPeriodId !== null && $originalCompanyId === null) {
            throw new DomainException('PeriodContext şirket/dönem bağlamı tutarsız.');
        }

        $gate = Gate::forUser($actor);
        $gate->authorize('reports.consolidated');

        $definition = $this->registry->query($reportKey)->definition();
        $gate->authorize($definition->permission);

        $selectedPeriods = $this->periods->select($actor, $companyId, $periodIds);
        $pageSize = max(1, (int) config('reporting.max_page_size', 250));
        $maxRows = max(1, (int) config('reporting.max_consolidated_rows', 50000));

        if ($request->limit < 1 || $request->offset < 0) {
            throw new DomainException('Çok dönemli rapor pagination parametreleri geçersiz.');
        }

        $allRows = [];
        $combinedTotals = [];
        $totalRows = 0;
        $first = null;
        $periodMetadata = [];

        try {
            foreach ($selectedPeriods as $period) {
                PeriodContext::useSystem($companyId, $period->id);

                $periodMetadata[] = [
                    'id' => (int) $period->id,
                    'year' => (int) $period->year,
                    'status' => (string) $period->status,
                ];

                $offset = 0;
                $expectedTotal = null;
                $periodFirst = null;

                do {
                    $result = $this->runReport->handle(
                        $reportKey,
                        new ReportRequest(
                            filters: $request->filters,
                            columns: $request->columns,
                            sort: $request->sort,
                            limit: $pageSize,
                            offset: $offset,
                        ),
                        $actor,
                    );

                    $periodFirst ??= $result;
                    $first ??= $result;
                    $expectedTotal ??= $result->totalRows;

                    if ($result->totalRows !== $expectedTotal) {
                        throw new DomainException(
                            "{$period->year} dönemi rapor verisi sorgu sırasında değişti.",
                        );
                    }

                    if (count($allRows) + count($result->rows) > $maxRows) {
                        throw new DomainException(
                            "Çok dönemli rapor {$maxRows} satır sınırını aşıyor; filtreyi daraltın.",
                        );
                    }

                    foreach ($result->rows as $row) {
                        $row['period_id'] = (int) $period->id;
                        $row['period_year'] = (int) $period->year;
                        $allRows[] = $row;
                    }

                    $offset += count($result->rows);

                    if ($result->rows === [] && $offset < $expectedTotal) {
                        throw new DomainException(
                            "{$period->year} dönemi pagination ilerleyemedi.",
                        );
                    }
                } while ($offset < $expectedTotal);

                $totalRows += $periodFirst->totalRows;
                $this->mergeTotals($combinedTotals, $periodFirst, $definition->totalMap());
            }
        } finally {
            PeriodContext::clear();

            if ($originalCompanyId && $originalPeriodId) {
                PeriodContext::useSystem($originalCompanyId, $originalPeriodId);
            }
        }

        if (! $first) {
            throw new DomainException('Çok dönemli rapor sonucu üretilemedi.');
        }

        $this->sortRows($allRows, $first->columns, $first->sort);
        $pageRows = array_slice($allRows, $request->offset, $request->limit);

        return new ConsolidatedReportResult(
            key: $first->key,
            title: $first->title.' — Çok Dönemli',
            columns: [
                new ReportColumnDefinition('period_year', 'Dönem', 'integer'),
                new ReportColumnDefinition('period_id', 'Dönem ID', 'integer', defaultVisible: false),
                ...$first->columns,
            ],
            rows: $pageRows,
            totals: $combinedTotals,
            totalRows: $totalRows,
            filters: $first->filters,
            sort: $first->sort,
            periods: $periodMetadata,
            definitionVersion: $first->definitionVersion,
            limit: $request->limit,
            offset: $request->offset,
        );
    }

    /**
     * @param  array<string,mixed>  $combined
     * @param  array<string,ReportTotalDefinition>  $totalDefinitions
     */
    private function mergeTotals(array &$combined, ReportResult $result, array $totalDefinitions): void
    {
        foreach ($result->totals as $key => $value) {
            if (! is_numeric((string) $value)) {
                throw new DomainException("Çok dönemli toplam numeric değil: {$key}.");
            }

            $type = $totalDefinitions[$key]->type ?? 'decimal';
            $scale = $type === 'integer' ? 0 : 8;
            $current = (string) ($combined[$key] ?? '0');
            $sum = bcadd($current, (string) $value, $scale);

            $combined[$key] = $scale === 0
                ? $sum
                : $this->trimDecimal($sum);
        }
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @param  list<ReportColumnDefinition>  $columns
     * @param  list<ReportSort>  $sort
     */
    private function sortRows(array &$rows, array $columns, array $sort): void
    {
        $columnTypes = [];

        foreach ($columns as $column) {
            $columnTypes[$column->key] = $column->type;
        }

        usort($rows, function (array $a, array $b) use ($columnTypes, $sort): int {
            foreach ($sort as $item) {
                $comparison = $this->compareValues(
                    $a[$item->key] ?? null,
                    $b[$item->key] ?? null,
                    $columnTypes[$item->key] ?? 'string',
                );

                if ($comparison !== 0) {
                    return $item->direction === 'desc' ? -$comparison : $comparison;
                }
            }

            $periodComparison = ((int) ($a['period_year'] ?? 0)) <=> ((int) ($b['period_year'] ?? 0));

            if ($periodComparison !== 0) {
                return $periodComparison;
            }

            $idA = $a['id'] ?? null;
            $idB = $b['id'] ?? null;

            if (is_numeric((string) $idA) && is_numeric((string) $idB)) {
                return bccomp((string) $idA, (string) $idB, 0);
            }

            return ((int) ($a['period_id'] ?? 0)) <=> ((int) ($b['period_id'] ?? 0));
        });
    }

    private function compareValues(mixed $left, mixed $right, string $type): int
    {
        if ($left === $right) {
            return 0;
        }

        if ($left === null || $left === '') {
            return 1;
        }

        if ($right === null || $right === '') {
            return -1;
        }

        if (in_array($type, ['integer', 'decimal', 'money', 'quantity'], true)
            && is_numeric((string) $left)
            && is_numeric((string) $right)) {
            return bccomp((string) $left, (string) $right, 8);
        }

        return strcmp((string) $left, (string) $right);
    }

    private function trimDecimal(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        $value = rtrim($value, '0');
        $value = rtrim($value, '.');

        return $value === '-0' || $value === '' ? '0' : $value;
    }
}
