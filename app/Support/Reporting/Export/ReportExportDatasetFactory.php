<?php

namespace App\Support\Reporting\Export;

use App\Actions\Reporting\RunReport;
use App\Models\User;
use App\Support\Reporting\ReportRegistry;
use App\Support\Reporting\ReportRequest;
use DomainException;

final class ReportExportDatasetFactory
{
    public function __construct(
        private readonly RunReport $runReport,
        private readonly ReportRegistry $registry,
    ) {}

    public function make(
        string $reportKey,
        ReportRequest $request,
        string $format,
        ?User $actor = null,
    ): ReportExportDataset {
        $format = strtolower(trim($format));

        if (! in_array($format, ['pdf', 'xlsx', 'csv'], true)) {
            throw new DomainException("Desteklenmeyen rapor export formatı: {$format}.");
        }

        $definition = $this->registry->query($reportKey)->definition();

        if (! in_array($format, $definition->exporters, true)) {
            throw new DomainException("{$definition->title} raporu {$format} export desteklemiyor.");
        }

        $pageSize = max(1, (int) config('reporting.max_page_size', 250));
        $rows = [];
        $offset = 0;
        $first = null;
        $expectedTotal = null;

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

            $first ??= $result;
            $expectedTotal ??= $result->totalRows;

            if ($result->totalRows !== $expectedTotal) {
                throw new DomainException('Rapor export sırasında dataset değişti; çıktı üretilmedi.');
            }

            array_push($rows, ...$result->rows);
            $offset += count($result->rows);

            if ($result->rows === [] && $offset < $expectedTotal) {
                throw new DomainException('Rapor export dataset pagination ilerleyemedi.');
            }
        } while ($offset < $expectedTotal);

        if (! $first) {
            throw new DomainException('Rapor export dataset üretilemedi.');
        }

        if (count($rows) !== $expectedTotal) {
            throw new DomainException('Rapor export satır sayısı ekran dataset ile uyuşmuyor.');
        }

        return new ReportExportDataset(
            key: $first->key,
            title: $first->title,
            columns: $first->columns,
            rows: $rows,
            totals: $first->totals,
            totalRows: $first->totalRows,
            filters: $first->filters,
            sort: $first->sort,
            definitionVersion: $first->definitionVersion,
        );
    }
}
