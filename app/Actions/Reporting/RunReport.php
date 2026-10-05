<?php

namespace App\Actions\Reporting;

use App\Models\User;
use App\Support\Reporting\ReportEngine;
use App\Support\Reporting\ReportRequest;
use App\Support\Reporting\ReportResult;

final class RunReport
{
    public function __construct(private readonly ReportEngine $engine) {}

    public function handle(
        string $key,
        ReportRequest $request,
        ?User $actor = null,
    ): ReportResult {
        return $this->engine->run($key, $request, $actor);
    }
}
