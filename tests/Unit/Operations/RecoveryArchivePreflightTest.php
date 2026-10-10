<?php

use App\Models\BackupRun;
use App\Support\Operations\RecoverySetArchive;

it('requires a completed verified backup before any archive filesystem operation', function (string $status): void {
    $run = new BackupRun;
    $run->status = $status;

    expect(fn () => (new RecoverySetArchive)->verifyAndExtract($run, '/tmp/mars-v4-not-created'))
        ->toThrow(RuntimeException::class);
})->with(['preparing', 'running', 'failed', 'aborted']);

it('rejects a completed backup with no archive manifest without extracting anything', function (): void {
    $run = new BackupRun;
    $run->status = 'done';
    $run->storage_disk = 'fake-v4-backup-storage';
    $run->master_backup_path = '';

    expect(fn () => (new RecoverySetArchive)->verifyAndExtract($run, '/tmp/mars-v4-not-created'))
        ->toThrow(RuntimeException::class);
});
