<?php

namespace App\Actions\Reporting;

use App\Models\User;
use App\Support\Reporting\Export\ReportExportArtifact;
use App\Support\Reporting\Export\ReportExportManager;
use App\Support\Reporting\ReportRequest;

final class ExportReport
{
    public function __construct(private readonly ReportExportManager $manager) {}

    public function handle(
        string $reportKey,
        ReportRequest $request,
        string $format,
        ?User $actor = null,
    ): ReportExportArtifact {
        return $this->manager->export($reportKey, $request, $format, $actor);
    }
}
