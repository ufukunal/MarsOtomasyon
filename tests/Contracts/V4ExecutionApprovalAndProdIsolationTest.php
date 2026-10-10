<?php

it('requires explicit approval before launching any PHP test suite', function (): void {
    $runner = file_get_contents(base_path('scripts/ci/test-v4.sh'));

    expect($runner)->toContain('MARS_TEST_EXECUTION_APPROVED')
        ->toContain('MARS_V4_EXECUTION_APPROVED')
        ->toContain('MARS_INTEGRATION_TESTS_APPROVED')
        ->toContain('I_APPROVE_LOCAL_TEST_ONLY')
        ->toContain('MARS_PERFORMANCE_TESTS_APPROVED')
        ->toContain('I_APPROVE_LOCAL_BENCHMARK')
        ->toContain('MARS_BROWSER_TESTS_APPROVED')
        ->toContain('I_APPROVE_LOCAL_BROWSER');
});

it('pins dedicated TEST DBs and the verified PROD VM Tailscale endpoint in PHPUnit', function (): void {
    $xml = file_get_contents(base_path('phpunit.xml'));

    expect($xml)->toContain('mars_test_master')
        ->toContain('mars_test')
        ->toContain('mars-prod-1.taila20365.ts.net')
        ->toContain('55432')
        ->not->toContain('192.168.239.132');
});

it('keeps browser tests bound to an isolated loopback application and opt-in UI mutations', function (): void {
    $browser = file_get_contents(base_path('tests/Browser/playwright.config.ts'));
    $journey = file_get_contents(base_path('tests/Browser/specs/isolated-product-contact-crud.spec.ts'));

    expect($browser)->toContain('127\\.')
        ->toContain('MARS_BROWSER_TESTS_APPROVED')
        ->toContain('MARS_BROWSER_BASE_URL');
    expect($journey)->toContain('MARS_BROWSER_TEST_MUTATIONS_APPROVED')
        ->toContain('I_APPROVE_LOCAL_UI_WRITES');
});

it('checks the remote test preflight and requires a least-privilege database role and Valkey ACL', function (): void {
    $preflight = file_get_contents(base_path('scripts/ci/v4-remote-preflight.php'));
    $runner = file_get_contents(base_path('scripts/ci/test-v4-direct.sh'));
    $launch = file_get_contents(base_path('scripts/ci/test-v4.sh'));

    expect($preflight)->toContain('mars_test_master')
        ->toContain('mars_test_period')
        ->toContain('has_database_privilege')
        ->toContain('rolsuper')
        ->toContain('rolcreatedb')
        ->toContain('rolcreaterole')
        ->toContain("rawCommand('ACL', 'WHOAMI')")
        ->toContain('mars:test:');

    expect($runner)->toContain('ISOLATION_CONFIRMED')
        ->toContain('MARS_V4_REMOTE_TEST_READY')
        ->toContain('tailscale');

    expect($launch)->toContain('MARS_V4_REMOTE_TEST_READY')
        ->toContain('MARS_VALKEY_TESTS_APPROVED');
});
