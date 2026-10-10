<?php

use App\Support\Operations\ArchivePeriodRestoreService;

it('accepts a strictly alphanumeric or underscore database name without opening a connection', function (): void {
    $service = (new ReflectionClass(ArchivePeriodRestoreService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(ArchivePeriodRestoreService::class, 'identifier');

    expect($method->invoke($service, 'mars_company_2026'))->toBe('mars_company_2026');
});

it('refuses SQL identifier injection and path fragments before database restore SQL is constructed', function (string $candidate): void {
    $service = (new ReflectionClass(ArchivePeriodRestoreService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(ArchivePeriodRestoreService::class, 'identifier');

    expect(fn () => $method->invoke($service, $candidate))->toThrow(RuntimeException::class);
})->with([
    'quoted' => ['company" WHERE 1=1'],
    'SQL separator' => ['company;DROP'],
    'relative path' => ['../../company'],
    'leading slash' => ['/var/lib/postgresql'],
    'space' => ['company 2026'],
    'unicode' => ['şirket_2026'],
]);

it('rejects unsafe runtime role identifiers before attempting database privilege changes', function (string $role): void {
    $service = (new ReflectionClass(ArchivePeriodRestoreService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(ArchivePeriodRestoreService::class, 'enforceRuntimeReadOnlyAccess');
    $old = config('operations.database.runtime_username');

    try {
        config(['operations.database.runtime_username' => $role]);
        expect(fn () => $method->invoke($service, 'mars_test_period'))
            ->toThrow(RuntimeException::class);
    } finally {
        config(['operations.database.runtime_username' => $old]);
    }
})->with(['', 'bad;role', 'role with spaces', '"quoted"', '9bad']);
