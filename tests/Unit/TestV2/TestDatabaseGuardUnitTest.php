<?php

use Tests\Support\TestDatabaseGuard;

it('V2-004 accepts only four explicitly allocated PostgreSQL test databases', function (string $database) {
    TestDatabaseGuard::assertSafe('testing', $database, '100.127.235.30', '55432', 'mars_test');
    expect(true)->toBeTrue();
})->with([
    'MarsProject_Master_Test_R1',
    'MarsProject_Master_Test_R2',
    'MarsProject_Master_Test_R3',
    'MarsProject_Coverage_Test',
]);

it('V2-004 rejects production-like or test-substring database names without opening a connection', function (string $database) {
    expect(fn () => TestDatabaseGuard::assertSafe('testing', $database, '100.127.235.30', '55432', 'mars_test'))
        ->toThrow(RuntimeException::class, 'PRODUCTION GUARD');
})->with([
    'MarsProject_Master',
    'MarsProject_Master_Test_Production',
    'my_test_db',
    'MarsProject_Master_Test_R4',
    'MarsProject_Coverage_Test_backups',
    '',
]);

it('V2-004 rejects wrong environment host port and user even for an allowlisted database', function (array $overrides) {
    $settings = array_replace([
        'environment' => 'testing',
        'database' => 'MarsProject_Master_Test_R1',
        'host' => '100.127.235.30',
        'port' => '55432',
        'username' => 'mars_test',
    ], $overrides);

    expect(fn () => TestDatabaseGuard::assertSafe(...array_values($settings)))
        ->toThrow(RuntimeException::class, 'PRODUCTION GUARD');
})->with([
    ['environment' => 'production'],
    ['environment' => 'local'],
    ['host' => '127.0.0.1'],
    ['host' => 'mars-prod.taila20365.ts.net'],
    ['port' => '5432'],
    ['username' => 'postgres'],
]);