<?php

namespace App\Support\Reporting\Export;

use App\Models\User;
use App\Support\Reporting\ReportRequest;
use DomainException;
use Illuminate\Support\Str;

final class ReportExportManager
{
    public function __construct(
        private readonly ReportExportDatasetFactory $datasets,
        private readonly CsvReportExporter $csv,
        private readonly XlsxReportExporter $xlsx,
        private readonly PdfReportExporter $pdf,
    ) {}

    public function export(
        string $reportKey,
        ReportRequest $request,
        string $format,
        ?User $actor = null,
    ): ReportExportArtifact {
        $format = strtolower(trim($format));
        $dataset = $this->datasets->make($reportKey, $request, $format, $actor);

        [$contents, $mimeType] = match ($format) {
            'csv' => [$this->csv->export($dataset), 'text/csv; charset=UTF-8'],
            'xlsx' => [
                $this->xlsx->export($dataset),
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'pdf' => [$this->pdf->export($dataset), 'application/pdf'],
            default => throw new DomainException("Desteklenmeyen rapor export formatı: {$format}."),
        };

        $base = Str::slug($dataset->title);

        if ($base === '') {
            $base = 'rapor';
        }

        return new ReportExportArtifact(
            format: $format,
            filename: $base.'-'.now(config('app.timezone'))->format('Ymd-His').'.'.$format,
            mimeType: $mimeType,
            contents: $contents,
        );
    }
}
