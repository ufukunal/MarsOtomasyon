<?php

it('pins V4 test identities to the existing PROD VM Tailscale services', function (): void {
    expect(config('database.connections.master.host'))->toBe('mars-prod-1.taila20365.ts.net')
        ->and((string) config('database.connections.master.port'))->toBe('55432')
        ->and(config('database.connections.master.database'))->toBe('mars_test_master')
        ->and(config('database.connections.master.username'))->toBe('mars_test')
        ->and(config('database.connections.period.host'))->toBe('mars-prod-1.taila20365.ts.net')
        ->and(config('database.redis.default.host'))->toBe('mars-prod-1.taila20365.ts.net')
        ->and(config('database.redis.default.username'))->toBe('mars_test')
        ->and(config('database.redis.options.prefix'))->toBe('mars:test:');
});

it('uses in-memory cache and sessions for ordinary tests', function (): void {
    expect(config('cache.default'))->toBe('array')
        ->and(config('session.driver'))->toBe('array')
        ->and(config('queue.default'))->toBe('sync');
});

it('uses direct Tailnet clients without provisioning database servers on CI', function (): void {
    $workflow = file_get_contents(base_path('.github/workflows/test-v4-manual.yml'));
    $direct = file_get_contents(base_path('scripts/ci/test-v4-direct.sh'));

    expect($workflow)->toContain('scripts/ci/test-v4-direct.sh')
        ->toContain('mars-prod-1.taila20365.ts.net')
        ->toContain('MARS_V4_DB_PASSWORD')
        ->toContain('MARS_V4_VALKEY_PASSWORD')
        ->not->toContain('mars-v4-postgres')
        ->not->toContain('mars-v4-valkey')
        ->not->toContain('MARS_V4_SSH_PASSWORD');

    expect($direct)->toContain('MARS_V4_REMOTE_TEST_READY')
        ->toContain('scripts/ci/v4-remote-preflight.php')
        ->not->toContain('docker run')
        ->not->toContain('docker compose')
        ->not->toContain('ssh -L');
});
