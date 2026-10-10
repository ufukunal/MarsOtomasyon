<?php

use App\Support\Operations\RecoverySetBackupService;
use App\Support\Operations\RestoreVerificationService;

it('refuses unknown backup triggers before any archive or database operation', function (string $trigger): void {
    $service = new RecoverySetBackupService;
    expect(fn () => $service->run($trigger))->toThrow(RuntimeException::class);
})->with(['root', 'deployment;DROP', 'migration', 'restore', '']);

it('uses namespaced temporary database names for recovery rehearsal', function (): void {
    $service = (new ReflectionClass(RestoreVerificationService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(RestoreVerificationService::class, 'tempDbName');
    $name = $method->invoke($service, 'master', 'abcdef0123456789abcdef0123456789');
    expect($name)->toStartWith('mars_verify_master_')
        ->and(strlen($name))->toBeLessThanOrEqual(63);
});
