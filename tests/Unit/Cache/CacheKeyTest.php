<?php

use App\Support\Cache\CacheKey;
use App\Support\Period\PeriodContext;

it('requires scoped context for tenant-specific cache keys', function (): void {
    PeriodContext::clear();
    expect(CacheKey::global('ready'))->toBe('g:ready');
    expect(fn () => CacheKey::master('balances'))->toThrow(RuntimeException::class);
    expect(fn () => CacheKey::period('balances'))->toThrow(RuntimeException::class);
});
