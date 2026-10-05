<?php

namespace App\Support\Reporting\Export;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Spatie\Browsershot\Browsershot;

final class PdfReportExporter
{
    public function __construct(private readonly ViewFactory $view) {}

    public function export(ReportExportDataset $dataset): string
    {
        $html = $this->view->make('reporting.exports.pdf', [
            'dataset' => $dataset,
            'generatedAt' => now(config('app.timezone')),
        ])->render();

        return Browsershot::html($html)
            ->format('A4')
            ->margins(14, 10, 16, 10)
            ->showBackground()
            ->showBrowserHeaderAndFooter()
            ->headerHtml('<div style="font-size:8px;width:100%;padding:0 10mm;color:#666;">MarsOtomasyon Rapor</div>')
            ->footerHtml('<div style="font-size:8px;width:100%;padding:0 10mm;color:#666;text-align:right;"><span class="pageNumber"></span> / <span class="totalPages"></span></div>')
            ->pdf();
    }
}
