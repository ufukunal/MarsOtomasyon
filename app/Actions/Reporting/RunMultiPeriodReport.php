<?php

namespace App\Actions\Reporting;

use App\Models\User;
use App\Support\Reporting\MultiPeriod\ConsolidatedReportResult;
use App\Support\Reporting\MultiPeriod\MultiPeriodQuery;
use App\Support\Reporting\ReportRequest;

final class RunMultiPeriodReport
{
    public function __construct(private readonly MultiPeriodQuery $query) {}

    /** @param list<int|string> $periodIds */
    public function handle(
        string $reportKey,
        array $periodIds,
        ReportRequest $request,
        ?User $actor = null,
        ?int $companyId = null,
    ): ConsolidatedReportResult {
        return $this->query->run(
            $reportKey,
            $periodIds,
            $request,
            $actor,
            $companyId,
        );
    }
}
