<?php

use App\Support\Operations\ReleaseActivationGate;

it('allows activation only when operational health is fully healthy', function (): void {
    ReleaseActivationGate::assertHealthy('healthy', 'release-check-1');

    expect(true)->toBeTrue();
});

it('rejects degraded and failed pre-activation health before activating the candidate release', function (string $status): void {
    expect(fn () => ReleaseActivationGate::assertHealthy($status, 'release-check-2'))
        ->toThrow(RuntimeException::class, "pre-activation health={$status}");
})->with(['degraded', 'failed', 'unknown', '']);
