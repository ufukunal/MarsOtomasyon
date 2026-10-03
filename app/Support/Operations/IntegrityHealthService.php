<?php

namespace App\Support\Operations;

use App\Models\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class IntegrityHealthService
{
    public function check(): array
    {
        $periods = Period::query()->where('status', 'active')->get();
        $failedChecks = 0;
        $checked = 0;
        $originalDatabase = config('database.connections.period.database');

        try {
            foreach ($periods as $period) {
                config(['database.connections.period.database' => $period->database_name]);
                DB::purge('period');

                if (! Schema::connection('period')->hasTable('integrity_reports')) {
                    $failedChecks++;
                    continue;
                }

                foreach (config('operations.integrity.checks', []) as $checkName) {
                    $checked++;

                    $latest = DB::connection('period')
                        ->table('integrity_reports')
                        ->where('check_name', $checkName)
                        ->orderByDesc('run_at')
                        ->first();

                    if (! $latest) {
                        $failedChecks++;
                        continue;
                    }

                    $age = CarbonImmutable::parse($latest->run_at)->diffInHours(now());

                    if ($age > (int) config('operations.integrity.max_age_hours', 72)
                        || (int) $latest->mismatch_count !== 0) {
                        $failedChecks++;
                    }
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Integrity health kontrolü başarısız.', ['exception' => $exception]);

            return ['ok' => false, 'status' => 'unavailable'];
        } finally {
            config(['database.connections.period.database' => $originalDatabase]);
            DB::purge('period');
        }

        if ($periods->isEmpty()) {
            return ['ok' => true, 'status' => 'not_configured'];
        }

        return [
            'ok' => $failedChecks === 0,
            'status' => $failedChecks === 0 ? 'healthy' : 'degraded',
            'periods_checked' => $periods->count(),
            'failed_checks' => $failedChecks,
            'checks_evaluated' => $checked,
        ];
    }
}
