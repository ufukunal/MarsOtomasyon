<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PostgreSqlFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgresql_18_is_the_only_transactional_test_database(): void
    {
        self::assertSame('pgsql', DB::connection()->getDriverName());

        $version = (int) DB::selectOne(
            "select current_setting('server_version_num')::int as version",
        )->version;

        self::assertGreaterThanOrEqual(180000, $version);
        self::assertLessThan(190000, $version);
    }

    public function test_pg_trgm_extension_is_installed_by_migration(): void
    {
        $installed = DB::selectOne(
            "select exists(select 1 from pg_extension where extname = 'pg_trgm') as installed",
        )->installed;

        self::assertTrue((bool) $installed);
    }

    public function test_default_transaction_isolation_is_read_committed(): void
    {
        $row = DB::selectOne('show default_transaction_isolation');

        self::assertSame('read committed', $row->default_transaction_isolation);
    }
}
