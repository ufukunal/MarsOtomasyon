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

it('uses only loopback and explicitly disposable database role names in the PHPUnit configuration', function (): void {
    $xml = file_get_contents(base_path('phpunit.xml'));

    expect($xml)->toContain('mars_test_master')
        ->toContain('mars_test')
        ->toContain('127.0.0.1')
        ->not->toContain('mars-prod-1.taila20365.ts.net')
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
