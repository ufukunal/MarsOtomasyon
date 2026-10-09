<?php

use App\Support\Cache\CacheKey;
use App\Support\Period\PeriodContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

it('v2 revoked membership disallows renewed company period context selection', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2REVOKE');
    $actor = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->actingAs($actor);
    DB::connection('master')->table('period_user_access')
        ->where('period_id', $period->id)
        ->where('user_id', $actor->id)
        ->update(['is_active' => false]);
    PeriodContext::clear();
    expect(fn () => PeriodContext::use($company->id, $period->id))
        ->toThrow(AuthorizationException::class);
});

it('v2 foreign company membership cannot reuse a period belonging to another company', function () {
    [$a, $aPeriod] = $this->createCompanyWithPeriod('V2CROSSA');
    $actor = $this->createUserWithPeriodAccess($a, $aPeriod, 'Yönetici');
    [$b, $bPeriod] = $this->createCompanyWithPeriod('V2CROSSB');
    $this->actingAs($actor);
    PeriodContext::clear();
    expect(fn () => PeriodContext::use($b->id, $bPeriod->id))
        ->toThrow(AuthorizationException::class);
});

it('v2 cache scoped keys include company and year but never an unscoped period suffix', function () {
    [$a, $aPeriod] = $this->createCompanyWithPeriod('V2CACHEA');
    $periodA = CacheKey::period('report-42');
    $masterA = CacheKey::master('report-42');
    [$b, $bPeriod] = $this->createCompanyWithPeriod('V2CACHEB');
    expect(CacheKey::period('report-42'))->not->toBe($periodA)
        ->and(CacheKey::master('report-42'))->not->toBe($masterA)
        ->and(CacheKey::global('report-42'))->toBe('g:report-42');
    PeriodContext::clear();
    expect(fn () => CacheKey::period('report-42'))->toThrow(RuntimeException::class)
        ->and(fn () => CacheKey::master('report-42'))->toThrow(RuntimeException::class);
});

it('v2 nested system callbacks restore the original physical period after an exception', function () {
    [$a, $aPeriod] = $this->createCompanyWithPeriod('V2NESTA');
    [$b, $bPeriod] = $this->createCompanyWithPeriod('V2NESTB');
    PeriodContext::useSystem($a->id, $aPeriod->id);
    expect(fn () => PeriodContext::withinSystem($bPeriod, function () use ($b) {
        expect(PeriodContext::companyId())->toBe($b->id);
        throw new RuntimeException('intentional nested failure');
    }))->toThrow(RuntimeException::class, 'intentional nested failure');
    expect(PeriodContext::companyId())->toBe($a->id)
        ->and(PeriodContext::periodId())->toBe($aPeriod->id)
        ->and((string) config('database.connections.period.database'))->toBe($aPeriod->database_name);
});