<?php

use Illuminate\Support\Facades\Artisan;

it('registers all integrity and operations commands without invoking them', function (): void {
    $commands = Artisan::all();
    foreach ([
        'integrity:all',
        'operations:health', 'operations:monitor',
        'channels:poll', 'channels:retry',
        'idempotency:prune', 'reports:prune-exports',
    ] as $name) {
        expect(array_key_exists($name, $commands))->toBeTrue("Unregistered command: {$name}");
    }
});
