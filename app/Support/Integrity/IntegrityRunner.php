<?php

namespace App\Support\Integrity;

use App\Models\IntegrityReport;

final class IntegrityRunner
{
    public function run(IntegrityCheck $check, bool $persist = true): IntegrityResult
    {
        $result = $check->run();

        if ($persist) {
            IntegrityReport::query()->create([
                'check_name' => $check->name(),
                'run_at' => now(),
                'checked_count' => $result->checked,
                'mismatch_count' => $result->mismatchCount(),
                'details' => [
                    'mismatches' => $result->mismatches,
                    'meta' => $result->meta,
                ],
                'duration_ms' => $result->durationMs,
            ]);
        }

        return $result;
    }
}
