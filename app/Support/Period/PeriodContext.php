<?php

namespace App\Support\Period;

use App\Exceptions\NoActivePeriodException;
use App\Exceptions\PeriodReadOnlyException;
use App\Models\Period;
use App\Support\Company\CompanyContext;
use Illuminate\Support\Facades\DB;

final class PeriodContext
{
    private static ?int $companyId = null;

    private static ?int $periodId = null;

    private static ?int $year = null;

    public static function use(int $companyId, int $periodId): Period
    {
        $period = Period::query()
            ->whereKey($periodId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        config(['database.connections.period.database' => $period->database_name]);

        DB::purge('period');
        DB::reconnect('period');

        self::$companyId = $companyId;
        self::$periodId = $period->id;
        self::$year = $period->year;

        CompanyContext::use($companyId);

        if (! app()->runningInConsole() && app()->bound('session')) {
            session([
                'active_company_id' => $companyId,
                'active_period_id' => $period->id,
                'active_year' => $period->year,
            ]);
        }

        return $period;
    }

    public static function companyId(): ?int
    {
        return self::$companyId ?? CompanyContext::id() ?? self::sessionValue('active_company_id');
    }

    public static function periodId(): ?int
    {
        return self::$periodId ?? self::sessionValue('active_period_id');
    }

    public static function year(): ?int
    {
        return self::$year ?? self::sessionValue('active_year');
    }

    public static function ensure(): void
    {
        if (! self::companyId() || ! self::periodId() || ! config('database.connections.period.database')) {
            throw new NoActivePeriodException('Şirket ve dönem seçilmedi.');
        }
    }

    public static function ensureWritable(): void
    {
        self::ensure();

        $period = Period::query()->findOrFail(self::periodId());

        if ($period->status !== 'active') {
            throw new PeriodReadOnlyException(
                sprintf('%d dönemi salt okunurdur.', $period->year),
            );
        }
    }

    public static function clear(): void
    {
        self::$companyId = null;
        self::$periodId = null;
        self::$year = null;

        config(['database.connections.period.database' => null]);
        DB::purge('period');
        CompanyContext::clear();

        if (! app()->runningInConsole() && app()->bound('session')) {
            session()->forget([
                'active_company_id',
                'active_period_id',
                'active_year',
            ]);
        }
    }

    private static function sessionValue(string $key): ?int
    {
        if (app()->runningInConsole() || ! app()->bound('session')) {
            return null;
        }

        $value = session($key);

        return $value === null ? null : (int) $value;
    }
}
