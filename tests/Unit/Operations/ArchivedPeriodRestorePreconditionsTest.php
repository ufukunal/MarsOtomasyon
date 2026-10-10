<?php

use App\Models\BackupRun;
use App\Models\Period;
use App\Support\Operations\ArchivePeriodRestoreService;

it('refuses restoring a period unless its status is archived before accessing PostgreSQL', function (): void {
    $service = (new ReflectionClass(ArchivePeriodRestoreService::class))->newInstanceWithoutConstructor();
    $period = new Period(['status' => 'active']);

    expect(fn () => $service->restore(new BackupRun, $period))->toThrow(RuntimeException::class);
});

it('rejects unverified recovery sets before performing any restoration', function (): void {
    $service = (new ReflectionClass(ArchivePeriodRestoreService::class))->newInstanceWithoutConstructor();
    $period = new Period(['status' => 'archived']);
    $backup = new BackupRun(['status' => 'done']);

    expect(fn () => $service->restore($backup, $period))->toThrow(RuntimeException::class);
});

it('refuses a recovery manifest that does not contain the requested archived period', function (): void {
    $service = (new ReflectionClass(ArchivePeriodRestoreService::class))->newInstanceWithoutConstructor();
    $period = new Period(['status' => 'archived', 'database_name' => 'mars_test_missing_period']);
    $backup = new BackupRun([
        'status' => 'verified',
        'verified_at' => now(),
        'period_manifest' => [
            ['database_name' => 'mars_test_different_period'],
        ],
    ]);

    expect(fn () => $service->restore($backup, $period))->toThrow(RuntimeException::class);
});
