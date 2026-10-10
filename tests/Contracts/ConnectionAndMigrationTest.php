<?php

use Illuminate\Support\Facades\DB;

it('declares separated master, active period and source period PostgreSQL connections', function (): void {
    foreach (['master', 'period', 'period_source'] as $connection) {
        expect(config("database.connections.{$connection}.driver"))->toBe('pgsql');
    }
    expect(config('database.connections.period.database'))->toBeNull()
        ->and(config('database.connections.period_source.database'))->toBeNull();
});

it('retains at least thirty master migrations and forty period migrations', function (): void {
    $master = glob(base_path('database/migrations/master/*.php'));
    $period = glob(base_path('database/migrations/period/*.php'));
    expect(count($master ?: []))->toBeGreaterThanOrEqual(30)
        ->and(count($period ?: []))->toBeGreaterThanOrEqual(44);
});

it('keeps separate Redis logical connections for cache, session and queue', function (): void {
    foreach (['default', 'cache', 'session', 'queue'] as $name) {
        expect(config("database.redis.{$name}"))->toBeArray()
            ->and(config("database.redis.{$name}.host"))->not->toBeEmpty();
    }
});
