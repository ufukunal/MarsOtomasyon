<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesCatalogFixtures;
use Tests\Support\CreatesPeriodDatabases;

abstract class TestCase extends BaseTestCase
{
    use CreatesCatalogFixtures;
    use CreatesPeriodDatabases;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->ensureSafeMasterTestDatabase();
        DB::purge('master');

        Artisan::call('migrate:fresh', [
            '--database' => 'master',
            '--path' => 'database/migrations/master',
            '--force' => true,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        $this->dropCreatedPeriodDatabases();

        parent::tearDown();
    }

    private function ensureSafeMasterTestDatabase(): void
    {
        $database = (string) config('database.connections.master.database');

        $host = (string) config('database.connections.master.host');
        $port = (string) config('database.connections.master.port');
        $username = (string) config('database.connections.master.username');

        $allowedDatabases = [
            'MarsProject_Master_Test_R1',
            'MarsProject_Master_Test_R2',
            'MarsProject_Master_Test_R3',
            'MarsProject_Coverage_Test',
        ];

        if (! app()->environment('testing')
            || ! in_array($database, $allowedDatabases, true)
            || $host !== '100.127.235.30'
            || $port !== '55432'
            || $username !== 'mars_test') {
            throw new RuntimeException('PRODUCTION GUARD: only isolated and explicitly approved CI test databases are writable.');
        }
        $password = (string) config('database.connections.master.password');

        $pdo = new PDO(
            "pgsql:host={$host};port={$port};dbname=postgres",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $statement = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = :name');
        $statement->execute(['name' => $database]);

        if (! $statement->fetchColumn()) {
            $pdo->exec(sprintf('CREATE DATABASE "%s"', $database));
        }
    }
}
