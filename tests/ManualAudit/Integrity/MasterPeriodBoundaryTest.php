<?php

use App\Exceptions\PeriodReadOnlyException;
use App\Models\Period\Brand;
use App\Support\Period\PeriodContext;
use App\Support\Period\SourcePeriodContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\ManualAudit\Support\AuditSource;
use Tests\TestCase;

uses(TestCase::class);

test('MP-026 fiziksel period veritabanları birbirinden izoledir', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('AUDISOA');
    $brand = Brand::query()->create(['name' => 'A-only', 'is_active' => true]);

    [$companyB, $periodB] = $this->createCompanyWithPeriod('AUDISOB');

    expect(PeriodContext::companyId())->toBe($companyB->id)
        ->and(PeriodContext::periodId())->toBe($periodB->id)
        ->and(Brand::query()->find($brand->id))->toBeNull();

    PeriodContext::useSystem($companyA->id, $periodA->id);
    expect(Brand::query()->findOrFail($brand->id)->name)->toBe('A-only');
});

test('MP-027 aynı numeric id farklı period DBde yanlış kayda çözülmez', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('AUDIDA');
    $brandA = Brand::query()->create(['name' => 'Period-A', 'is_active' => true]);

    [$companyB, $periodB] = $this->createCompanyWithPeriod('AUDIDB');
    $brandB = Brand::query()->create(['name' => 'Period-B', 'is_active' => true]);

    PeriodContext::useSystem($companyA->id, $periodA->id);
    expect(Brand::query()->findOrFail($brandA->id)->name)->toBe('Period-A');

    PeriodContext::useSystem($companyB->id, $periodB->id);
    expect(Brand::query()->findOrFail($brandB->id)->name)->toBe('Period-B');
});

test('MP-028 normal context geçişi cross-company yetkisiz erişimi reddeder', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('AUDAUTH1');
    [$companyB, $periodB] = $this->createCompanyWithPeriod('AUDAUTH2');
    $user = $this->createUserWithPeriodAccess($companyA, $periodA, 'Yönetici');

    $this->actingAs($user);

    expect(fn () => PeriodContext::use($companyB->id, $periodB->id))
        ->toThrow(AuthorizationException::class);
});

test('MP-029 period erişimi company ve period ACL birlikte gerektirir', function () {
    $source = AuditSource::read('app/Support/Period/PeriodContext.php');

    expect($source)
        ->toContain("table('company_user')")
        ->toContain("table('period_user_access')")
        ->toContain("where('is_active', true)");
});

test('MP-030 useSystem doğru fiziksel DByi aktive eder', function () {
    [$company, $period] = $this->createCompanyWithPeriod('AUDSYS');

    PeriodContext::clear();
    PeriodContext::useSystem($company->id, $period->id);

    expect(config('database.connections.period.database'))->toBe($period->database_name)
        ->and(PeriodContext::companyId())->toBe($company->id)
        ->and(PeriodContext::periodId())->toBe($period->id);
});

test('MP-031 withinSystem başarılı callback sonrası önceki contexti geri yükler', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('AUDWITHA');
    [$companyB, $periodB] = $this->createCompanyWithPeriod('AUDWITHB');

    PeriodContext::withinSystem($periodA, function () use ($periodA): void {
        expect(PeriodContext::periodId())->toBe($periodA->id);
    });

    expect(PeriodContext::companyId())->toBe($companyB->id)
        ->and(PeriodContext::periodId())->toBe($periodB->id);
});

test('MP-032 withinSystem exception sonrası önceki contexti geri yükler', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('AUDEXA');
    [$companyB, $periodB] = $this->createCompanyWithPeriod('AUDEXB');

    try {
        PeriodContext::withinSystem($periodA, fn () => throw new RuntimeException('expected'));
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('expected');
    }

    expect(PeriodContext::companyId())->toBe($companyB->id)
        ->and(PeriodContext::periodId())->toBe($periodB->id);
});

test('MP-033 clear static ve DB period contextini temizler', function () {
    $this->createCompanyWithPeriod('AUDCLEAR');

    PeriodContext::clear();

    expect(PeriodContext::companyId())->toBeNull()
        ->and(PeriodContext::periodId())->toBeNull()
        ->and(config('database.connections.period.database'))->toBeNull();
});

test('MP-034 release period database configini temizler', function () {
    $this->createCompanyWithPeriod('AUDREL');

    PeriodContext::release();

    expect(config('database.connections.period.database'))->toBeNull();
});

test('MP-035 closed period mutationa kapalıdır', function () {
    $this->createCompanyWithPeriod('AUDCLOSED', 2026, 'closed');

    expect(fn () => PeriodContext::ensureWritable())
        ->toThrow(PeriodReadOnlyException::class);
});

test('MP-036 archived period mutationa kapalıdır', function () {
    $this->createCompanyWithPeriod('AUDARCH', 2026, 'archived');

    expect(fn () => PeriodContext::ensureWritable())
        ->toThrow(PeriodReadOnlyException::class);
});

test('MP-037 active period writable kontrolden geçer', function () {
    $this->createCompanyWithPeriod('AUDACTIVE');

    PeriodContext::ensureWritable();

    expect(PeriodContext::periodId())->not->toBeNull();
});

test('MP-038 period dışı tarih EnsurePeriodOpen tarafından reddedilir', function () {
    $source = AuditSource::read('app/Actions/Periods/EnsurePeriodOpen.php');

    expect($source)
        ->toContain('PeriodContext::ensureWritable()')
        ->toContain('PeriodContext::year()');
});

test('MP-039 context kullanan system wrapper finally ile cleanup yapar', function () {
    expect(AuditSource::read('app/Support/Period/PeriodContext.php'))
        ->toMatch('/withinSystem[\s\S]+?finally\s*\{[\s\S]+?self::clear\(\)/');
});

test('MP-040 çoklu period geçişinden sonra önceki context kaybolmaz', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('AUDMULTA');
    [$companyB, $periodB] = $this->createCompanyWithPeriod('AUDMULTB');

    PeriodContext::useSystem($companyA->id, $periodA->id);
    PeriodContext::withinSystem($periodB, fn () => expect(PeriodContext::periodId())->toBe($periodB->id));

    expect(PeriodContext::periodId())->toBe($periodA->id);
});

test('MP-041 source period connection clear ile sıfırlanır', function () {
    [, $period] = $this->createCompanyWithPeriod('AUDSRC');

    SourcePeriodContext::usePeriod($period);
    expect(config('database.connections.period_source.database'))->toBe($period->database_name);

    SourcePeriodContext::clear();
    expect(config('database.connections.period_source.database'))->toBeNull();
});

test('MP-042 company period mismatch system activationda dahi bulunamaz', function () {
    [$companyA] = $this->createCompanyWithPeriod('AUDMISSA');
    [, $periodB] = $this->createCompanyWithPeriod('AUDMISSB');

    expect(fn () => PeriodContext::useSystem($companyA->id, $periodB->id))
        ->toThrow(ModelNotFoundException::class);
});
