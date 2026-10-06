<?php

namespace App\Actions\Periods;

use App\Actions\ReferenceData\SeedPeriodReferenceData;
use App\Models\Company;
use App\Models\Period;
use App\Support\Period\PeriodContext;
use App\Support\Period\PeriodSchemaVersion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class CreatePeriod
{
    public function handle(Company $company, int $year): Period
    {
        $dbName = sprintf('%s_%d', $company->db_prefix, $year);

        if (Period::query()->where('company_id', $company->id)->where('year', $year)->exists()
            || Period::query()->where('database_name', $dbName)->exists()) {
            throw new RuntimeException("{$dbName} zaten var.");
        }

        if (DB::connection('master')->table('pg_database')->where('datname', $dbName)->exists()) {
            throw new RuntimeException("{$dbName} fiziksel veritabanı zaten var.");
        }

        $databaseCreated = false;
        $period = null;

        try {
            DB::connection('master')->statement(sprintf('CREATE DATABASE "%s"', $dbName));
            $databaseCreated = true;

            $period = Period::query()->create([
                'company_id' => $company->id,
                'year' => $year,
                'database_name' => $dbName,
                'starts_on' => "{$year}-01-01",
                'ends_on' => "{$year}-12-31",
                'status' => 'active',
            ]);

            PeriodContext::withinSystem($period, function () use ($dbName, $period): void {
                $exitCode = Artisan::call('migrate', [
                    '--database' => 'period',
                    '--path' => 'database/migrations/period',
                    '--force' => true,
                ]);

                if ($exitCode !== 0) {
                    throw new RuntimeException("{$dbName} period migration başarısız oldu.");
                }

                app(SeedPeriodReferenceData::class)->handle();
                $this->grantRuntimeRole($dbName);

                $schemaVersion = app(PeriodSchemaVersion::class)->currentDatabaseVersion();

                Period::query()
                    ->whereKey($period->id)
                    ->update(['schema_version' => $schemaVersion]);

                $period->schema_version = $schemaVersion;
            });

            return $period;
        } catch (Throwable $exception) {
            if ($period?->exists) {
                $period->delete();
            }

            if ($databaseCreated) {
                DB::connection('master')->statement(sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', $dbName));
            }

            throw $exception;
        }
    }

    private function grantRuntimeRole(string $databaseName): void
    {
        $role = trim((string) config('operations.database.runtime_username'));

        if ($role === '') {
            throw new RuntimeException('Runtime DB username tanımlı olmalıdır.');
        }

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $role)
            || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $databaseName)) {
            throw new RuntimeException('Runtime DB role/database identifier geçersiz.');
        }

        $quotedRole = '"'.$role.'"';
        $quotedDatabase = '"'.$databaseName.'"';

        DB::connection('period')->statement('REVOKE CREATE ON SCHEMA public FROM PUBLIC');
        DB::connection('period')->statement("GRANT CONNECT ON DATABASE {$quotedDatabase} TO {$quotedRole}");
        DB::connection('period')->statement("GRANT USAGE ON SCHEMA public TO {$quotedRole}");
        DB::connection('period')->statement(
            "GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO {$quotedRole}"
        );
        DB::connection('period')->statement(
            "GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO {$quotedRole}"
        );
        DB::connection('period')->statement(
            "ALTER DEFAULT PRIVILEGES IN SCHEMA public
             GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$quotedRole}"
        );
        DB::connection('period')->statement(
            "ALTER DEFAULT PRIVILEGES IN SCHEMA public
             GRANT USAGE, SELECT ON SEQUENCES TO {$quotedRole}"
        );
    }
}
