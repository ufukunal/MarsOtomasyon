<?php

namespace App\Contracts\Reporting;

use App\Support\Reporting\ReportDefinition;
use App\Support\Reporting\ReportExecutionContext;
use App\Support\Reporting\ReportQueryResult;

interface ReportQuery
{
    public function definition(): ReportDefinition;

    public function execute(ReportExecutionContext $context): ReportQueryResult;
}
