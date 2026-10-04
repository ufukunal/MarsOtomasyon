<?php

namespace App\Console\Commands\Concerns;

use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityRunner;

trait RunsIntegrityCheck
{
    protected function runCheck(IntegrityCheck $check): int
    {
        $result = app(IntegrityRunner::class)->run($check);

        $this->line(sprintf(
            '%s: checked=%d mismatch=%d duration=%dms',
            $check->name(),
            $result->checked,
            $result->mismatchCount(),
            $result->durationMs,
        ));

        return $result->mismatchCount() > 0 ? self::FAILURE : self::SUCCESS;
    }
}
