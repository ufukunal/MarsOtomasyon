<?php

namespace App\Support\Period;

use App\Models\Period;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SourcePeriodContext
{
    public static function use(int $sourceCompanyId): Period
    {
        PeriodContext::ensure();

        $year = PeriodContext::year();

        $period = Period::query()
            ->where('company_id', $sourceCompanyId)
            ->where('year', $year)
            ->whereIn('status', ['active', 'closed'])
            ->first();

        if (! $period) {
            throw new RuntimeException("Kaynak şirket için {$year} dönemi bulunamadı.");
        }

        return self::usePeriod($period);
    }

    public static function usePeriod(Period $period): Period
    {
        config(['database.connections.period_source.database' => $period->database_name]);
        DB::purge('period_source');
        DB::reconnect('period_source');

        return $period;
    }

    public static function clear(): void
    {
        config(['database.connections.period_source.database' => null]);
        DB::purge('period_source');
    }
}
