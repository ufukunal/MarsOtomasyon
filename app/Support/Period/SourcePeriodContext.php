<?php

namespace App\Support\Period;

use App\Models\Period;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SourcePeriodContext
{
    private static ?int $sourceCompanyId = null;

    private static ?int $sourcePeriodId = null;

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

        config(['database.connections.period_source.database' => $period->database_name]);
        DB::purge('period_source');
        DB::reconnect('period_source');

        self::$sourceCompanyId = $sourceCompanyId;
        self::$sourcePeriodId = $period->id;

        return $period;
    }

    public static function clear(): void
    {
        self::$sourceCompanyId = null;
        self::$sourcePeriodId = null;

        config(['database.connections.period_source.database' => null]);
        DB::purge('period_source');
    }
}
