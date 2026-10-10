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

it('requires SSH-forwarded services rather than installing database servers on runners', function (): void {
    $workflow = file_get_contents(base_path('.github/workflows/test-v4-manual.yml'));
    $tunnel = file_get_contents(base_path('scripts/ci/test-v4-remote.sh'));

    expect($workflow)->toContain('MARS_V4_REMOTE_PG_PORT')
        ->toContain('MARS_V4_REMOTE_REDIS_PORT')
        ->toContain('MARS_V4_SSH_PRIVATE_KEY')
        ->not->toContain('mars-v4-postgres')
        ->not->toContain('mars-v4-valkey');

    expect($tunnel)->toContain('127.0.0.1:5432:127.0.0.1:55432')
        ->toContain('127.0.0.1:6379:127.0.0.1:6379')
        ->toContain('StrictHostKeyChecking=yes')
        ->not->toContain('docker run')
        ->not->toContain('docker compose');
});
