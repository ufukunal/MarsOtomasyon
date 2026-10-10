<?php

use Illuminate\Support\Facades\Artisan;

it('registers operational maintenance commands with distinct resolved names', function (): void {
    $names = array_keys(Artisan::all());

    foreach ([
        'operations:health',
        'operations:monitor',
        'operations:backup',
        'operations:restore-verify',
        'operations:deploy',
        'operations:rollback',
        'integrity:all',
        'idempotency:prune',
        'reports:prune-exports',
        'channels:poll',
        'channels:retry',
    ] as $command) {
        expect($names)->toContain($command);
    }
});

it('keeps the scheduler registered but does not invoke any maintenance task', function (): void {
    $source = file_get_contents(base_path('routes/console.php'));

    foreach ([
        'backup:clean',
        'RunRecoverySetBackupJob',
        'VerifyLatestRecoverySetBackupJob',
        'integrity:all',
        'channels:poll',
        'channels:retry',
        'operations:health',
        'queue-worker-heartbeat',
    ] as $entry) {
        expect($source)->toContain($entry);
    }
});
