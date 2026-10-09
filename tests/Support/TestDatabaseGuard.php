<?php

namespace Tests\Support;

use RuntimeException;

final class TestDatabaseGuard
{
    /** @var list<string> */
    private const ALLOWED_DATABASES = [
        'MarsProject_Master_Test_R1',
        'MarsProject_Master_Test_R2',
        'MarsProject_Master_Test_R3',
        'MarsProject_Coverage_Test',
    ];

    public static function assertSafe(
        string $environment,
        string $database,
        string $host,
        string $port,
        string $username,
    ): void {
        if ($environment !== 'testing'
            || ! in_array($database, self::ALLOWED_DATABASES, true)
            || $host !== '100.127.235.30'
            || $port !== '55432'
            || $username !== 'mars_test') {
            throw new RuntimeException(
                'PRODUCTION GUARD: only isolated and explicitly approved CI test databases are writable.',
            );
        }
    }
}