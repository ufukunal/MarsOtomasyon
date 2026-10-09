<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesCatalogFixtures;
use Tests\Support\CreatesPeriodDatabases;
use Tests\Support\TestDatabaseGuard;

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

        TestDatabaseGuard::assertSafe(app()->environment(), $database, $host, $port, $username);

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
