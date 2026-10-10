<?php

it('pins the suite to disposable local infrastructure, never production Tailnet', function (): void {
    expect(config('database.connections.master.host'))->toBe('127.0.0.1')
        ->and(config('database.connections.master.database'))->toBe('mars_test_master')
        ->and(config('database.connections.master.username'))->toBe('mars_test')
        ->and(config('database.connections.period.host'))->toBe('127.0.0.1')
        ->and(config('database.redis.default.host'))->toBe('127.0.0.1')
        ->and(config('database.redis.options.prefix'))->toBe('mars:test:');
});

it('uses in-memory cache and sessions for ordinary tests', function (): void {
    expect(config('cache.default'))->toBe('array')
        ->and(config('session.driver'))->toBe('array')
        ->and(config('queue.default'))->toBe('sync');
});
