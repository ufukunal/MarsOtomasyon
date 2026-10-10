<?php

use App\Support\Operations\RestoreVerificationService;

it('builds PostgreSQL restore CLI arguments as separate argv entries with fail-fast SQL handling', function (): void {
    $service = (new ReflectionClass(RestoreVerificationService::class))->newInstanceWithoutConstructor();
    $arguments = (new ReflectionMethod(RestoreVerificationService::class, 'psqlArgs'))
        ->invoke($service, 'mars_verify_master_v4', ['--file=/tmp/mars-v4-fixture.sql']);

    expect($arguments)->toContain('psql')
        ->toContain('--dbname=mars_verify_master_v4')
        ->toContain('--set=ON_ERROR_STOP=1')
        ->toContain('--file=/tmp/mars-v4-fixture.sql')
        ->and($arguments[0])->toBe('psql');
});

it('rejects invalid temporary PostgreSQL identifiers before constructing CREATE or DROP statements', function (string $name): void {
    $service = (new ReflectionClass(RestoreVerificationService::class))->newInstanceWithoutConstructor();
    $identifier = new ReflectionMethod(RestoreVerificationService::class, 'assertIdentifier');

    expect(fn () => $identifier->invoke($service, $name))->toThrow(RuntimeException::class);
})->with([
    'SQL terminator' => ['v4;DROP_DATABASE'],
    'quoted role' => ['v4"role'],
    'path fragment' => ['../master'],
    'spaces' => ['v4 main'],
    'hyphen' => ['v4-prod'],
    'non-ASCII identifier' => ['şirket_2027'],
]);

it('keeps host credentials out of shell argument strings', function (): void {
    $service = (new ReflectionClass(RestoreVerificationService::class))->newInstanceWithoutConstructor();
    $oldPassword = config('database.connections.master.password');

    try {
        config(['database.connections.master.password' => 'V4_FAKE_PASSWORD_ONLY']);
        $arguments = (new ReflectionMethod(RestoreVerificationService::class, 'psqlArgs'))
            ->invoke($service, 'mars_verify_master_v4', []);

        expect(implode(' ', $arguments))->not->toContain('V4_FAKE_PASSWORD_ONLY');
        $env = (new ReflectionMethod(RestoreVerificationService::class, 'pgEnv'))->invoke($service);
        expect($env)->toBe(['PGPASSWORD' => 'V4_FAKE_PASSWORD_ONLY']);
    } finally {
        config(['database.connections.master.password' => $oldPassword]);
    }
});
