<?php

namespace Tests\Support;

use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;
use RuntimeException;

final class IsolatedPostgres
{
    public static function approved(): void
    {
        if (getenv('MARS_INTEGRATION_TESTS_APPROVED') !== 'I_APPROVE_LOCAL_TEST_ONLY') {
            Assert::markTestSkipped('Standalone local PostgreSQL approval is absent.');
        }

        foreach (['master', 'period'] as $connection) {
            $config = config("database.connections.{$connection}");
            if (($config['driver'] ?? '') !== 'pgsql'
                || ($config['host'] ?? '') !== '127.0.0.1'
                || (string) ($config['username'] ?? '') !== 'mars_test') {
                throw new RuntimeException("Unsafe {$connection} database; refusing integration tests.");
            }
        }

        if (config('database.connections.master.database') !== 'mars_test_master') {
            throw new RuntimeException('Unsafe master database name; refusing integration tests.');
        }

        config(['database.connections.period.database' => 'mars_test_period']);

        if (config('database.connections.period.database') === config('database.connections.master.database')) {
            throw new RuntimeException('Master and period database names must differ.');
        }
    }

    /**
     * The callback uses only existing, dedicated local test databases.
     * All inserted fixtures are rolled back, including on exceptions.
     * Do not use this with app code that purges active DB connections mid-callback.
     *
     * @param callable(int,int):mixed $callback company ID and period ID
     */
    public static function withActivePeriod(callable $callback): mixed
    {
        self::approved();

        $master = DB::connection('master');
        $master->beginTransaction();

        $periodConnection = null;
        $active = false;

        try {
            $unique = strtolower(Str::random(10));
            $companyId = $master->table('companies')->insertGetId([
                'code' => $unique,
                'name' => 'V4 disposable fixture',
                'db_prefix' => 'v4_'.$unique,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $periodId = $master->table('periods')->insertGetId([
                'company_id' => $companyId,
                'year' => 2026,
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
                'database_name' => 'mars_test_period',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            PeriodContext::useSystem((int) $companyId, (int) $periodId);
            $active = true;

            $periodConnection = DB::connection('period');
            $periodConnection->beginTransaction();

            return $callback((int) $companyId, (int) $periodId);
        } finally {
            if ($periodConnection !== null && $periodConnection->transactionLevel() > 0) {
                $periodConnection->rollBack();
            }
            if ($active) {
                PeriodContext::clear();
            }
            if ($master->transactionLevel() > 0) {
                $master->rollBack();
            }
        }
    }

    /** @return array{product:int,unit:int,location:int} */
    public static function productAndLocation(): array
    {
        $db = DB::connection('period');
        $suffix = strtolower(Str::random(10));
        $unit = $db->table('units')->insertGetId([
            'code' => 'U'.$suffix, 'name' => 'Unit '.$suffix,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $location = $db->table('locations')->insertGetId([
            'code' => 'L'.$suffix, 'name' => 'Warehouse '.$suffix, 'kind' => 'warehouse',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $product = $db->table('products')->insertGetId([
            'code' => 'P'.$suffix, 'name' => 'Product '.$suffix, 'unit_id' => $unit,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['product' => (int) $product, 'unit' => (int) $unit, 'location' => (int) $location];
    }
}
