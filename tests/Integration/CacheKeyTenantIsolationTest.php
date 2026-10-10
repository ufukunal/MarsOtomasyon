<?php

use App\Support\Cache\CacheKey;
use App\Support\Period\PeriodContext;
use Tests\Support\IsolatedPostgres;

it('requires a tenant context for master and period business cache keys', function (): void {
    PeriodContext::clear();

    expect(fn () => CacheKey::master('products'))->toThrow(RuntimeException::class);
    expect(fn () => CacheKey::period('stock'))->toThrow(RuntimeException::class);
    expect(CacheKey::global('operations'))->toBe('g:operations');
});

it('prefixes each cache key with its company and accounting year', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        expect(CacheKey::master('stock'))->toBe("c{$companyId}:stock")
            ->and(CacheKey::period('stock'))->toBe("c{$companyId}:y2026:stock");
    });
});
