<?php

use App\Support\Operations\DatabasePrivilegeVerifier;

it('rejects missing runtime database account names without a database query', function (): void {
    $old = config('operations.database.runtime_username');

    try {
        config(['operations.database.runtime_username' => '']);
        $messages = (new DatabasePrivilegeVerifier)->failures();

        expect($messages)->toHaveCount(1)
            ->and($messages[0])->toContain('RUNTIME_DB_USERNAME');
    } finally {
        config(['operations.database.runtime_username' => $old]);
    }
});

it('rejects SQL metacharacters and malformed runtime account identifiers', function (string $role): void {
    $old = config('operations.database.runtime_username');

    try {
        config(['operations.database.runtime_username' => $role]);
        $messages = (new DatabasePrivilegeVerifier)->failures();

        expect($messages)->toHaveCount(1)
            ->and($messages[0])->toContain('identifier');
    } finally {
        config(['operations.database.runtime_username' => $old]);
    }
})->with(['bad;DROP ROLE admin', 'role with spaces', '4invalid', 'quoted"user']);
