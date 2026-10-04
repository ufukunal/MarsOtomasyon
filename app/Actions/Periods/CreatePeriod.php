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

            PeriodContext::useSystem($company->id, $period->id);

            $exitCode = Artisan::call('migrate', [
                '--database' => 'period',
                '--path' => 'database/migrations/period',
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                throw new RuntimeException("{$dbName} period migration başarısız oldu.");
            }

            app(SeedPeriodReferenceData::class)->handle();

            $schemaVersion = app(PeriodSchemaVersion::class)->currentDatabaseVersion();

            Period::query()
                ->whereKey($period->id)
                ->update(['schema_version' => $schemaVersion]);

            $period->schema_version = $schemaVersion;

            return $period;
        } catch (Throwable $exception) {
            DB::purge('period');

            if ($period?->exists) {
                $period->delete();
            }

            PeriodContext::clear();

            if ($databaseCreated) {
                DB::connection('master')->statement(sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', $dbName));
            }

            throw $exception;
        }
    }
}
