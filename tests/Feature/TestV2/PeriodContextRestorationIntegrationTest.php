<?php

use App\Models\Period\Product;
use App\Support\Cache\CacheKey;
use App\Support\Company\CompanyContext;
use App\Support\Period\PeriodContext;

it('v2 nested system period context temporarily switches physical database and restores caller context', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('V2CTXA');
    $product = $this->createTestProduct(['code' => 'V2-CONTEXT-A']);
    $keyA = CacheKey::period('catalog');
    [$companyB, $periodB] = $this->createCompanyWithPeriod('V2CTXB');
    $keyB = CacheKey::period('catalog');

    $result = PeriodContext::withinSystem($periodA, function () use ($product, $companyA, $periodA, $keyA): string {
        expect(PeriodContext::periodId())->toBe($periodA->id)
            ->and(CompanyContext::id())->toBe($companyA->id)
            ->and(CacheKey::period('catalog'))->toBe($keyA)
            ->and(Product::query()->whereKey($product->id)->where('code', 'V2-CONTEXT-A')->exists())->toBeTrue();

        return 'selected A';
    });

    expect($result)->toBe('selected A')
        ->and(PeriodContext::periodId())->toBe($periodB->id)
        ->and(CompanyContext::id())->toBe($companyB->id)
        ->and(CacheKey::period('catalog'))->toBe($keyB)
        ->and(Product::query()->where('code', 'V2-CONTEXT-A')->exists())->toBeFalse();
});

it('v2 system period context restores parent connection even when child task throws', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('V2CTXEXA');
    [$companyB, $periodB] = $this->createCompanyWithPeriod('V2CTXEXB');
    $databaseB = (string) config('database.connections.period.database');

    expect(fn () => PeriodContext::withinSystem($periodA, function (): never {
        throw new \RuntimeException('V2 injected nested failure');
    }))->toThrow(\RuntimeException::class, 'V2 injected nested failure');

    expect(PeriodContext::periodId())->toBe($periodB->id)
        ->and(CompanyContext::id())->toBe($companyB->id)
        ->and(config('database.connections.period.database'))->toBe($databaseB);
});