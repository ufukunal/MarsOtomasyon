<?php

namespace App\Support\Period;

use App\Exceptions\NoActivePeriodException;
use App\Exceptions\PeriodReadOnlyException;
use App\Models\Period;
use App\Support\Company\CompanyContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class PeriodContext
{
    private static ?int $companyId = null;

    private static ?int $periodId = null;

    private static ?int $year = null;

    public static function use(int $companyId, int $periodId): Period
    {
        self::assertAuthenticatedUserAccess($companyId, $periodId);

        return self::activate($companyId, $periodId);
    }

    /**
     * Güvenilir CLI/job/kurulum akışları için kullanıcı erişim kontrolünü atlar.
     * HTTP request iş kodu normalde use() kullanmalıdır.
     */
    public static function useSystem(int $companyId, int $periodId): Period
    {
        return self::activate($companyId, $periodId);
    }

    private static function activate(int $companyId, int $periodId): Period
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

    public static function withinSystem(Period $period, Closure $callback): mixed
    {
        $oldCompanyId = self::companyId();
        $oldPeriodId = self::periodId();

        try {
            self::useSystem((int) $period->company_id, (int) $period->id);

            return $callback($period);
        } finally {
            self::clear();

            if ($oldCompanyId && $oldPeriodId) {
                self::useSystem($oldCompanyId, $oldPeriodId);
            }
        }
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

    public static function release(): void
    {
        self::$companyId = null;
        self::$periodId = null;
        self::$year = null;

        config(['database.connections.period.database' => null]);
        DB::purge('period');
    }

    public static function clear(): void
    {
        self::release();
        CompanyContext::clear();

        if (! app()->runningInConsole() && app()->bound('session')) {
            session()->forget([
                'active_company_id',
                'active_period_id',
                'active_year',
            ]);
        }
    }

    private static function assertAuthenticatedUserAccess(int $companyId, int $periodId): void
    {
        if (! Auth::check()) {
            throw new AuthorizationException('Dönem bağlamı için oturum açmış kullanıcı gereklidir.');
        }

        $userId = Auth::id();

        $companyAllowed = DB::connection('master')
            ->table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->exists();

        $periodAllowed = DB::connection('master')
            ->table('period_user_access')
            ->where('period_id', $periodId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->exists();

        if (! $companyAllowed || ! $periodAllowed) {
            throw new AuthorizationException('Bu şirket/dönem bağlamına erişim izniniz yok.');
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
