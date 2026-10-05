<?php

namespace App\Support\Operations;

use App\Models\Period;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class DatabasePrivilegeVerifier
{
    /** @return list<string> */
    public function failures(): array
    {
        $role = trim((string) config('operations.database.runtime_username'));

        if ($role === '') {
            return ['RUNTIME_DB_USERNAME tanımlı olmalıdır.'];
        }

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $role)) {
            return ['RUNTIME_DB_USERNAME geçerli PostgreSQL role identifier olmalıdır.'];
        }

        $failures = [];

        try {
            $row = DB::connection('master')->selectOne(
                'SELECT rolname, rolsuper, rolcreatedb, rolcreaterole, rolreplication
                 FROM pg_roles WHERE rolname = ?',
                [$role],
            );

            if (! $row) {
                return ["Runtime DB role bulunamadı: {$role}."];
            }

            foreach ([
                'rolsuper' => 'SUPERUSER',
                'rolcreatedb' => 'CREATEDB',
                'rolcreaterole' => 'CREATEROLE',
                'rolreplication' => 'REPLICATION',
            ] as $field => $label) {
                if ((bool) $row->{$field}) {
                    $failures[] = "Runtime DB role {$label} privilege taşımamalıdır.";
                }
            }

            $databases = [(string) config('database.connections.master.database')];

            foreach (Period::query()
                ->whereIn('status', ['active', 'closed'])
                ->pluck('database_name') as $database) {
                $databases[] = (string) $database;
            }

            foreach (array_values(array_unique(array_filter($databases))) as $database) {
                $connect = DB::connection('master')->selectOne(
                    "SELECT has_database_privilege(?, ?, 'CONNECT') AS allowed",
                    [$role, $database],
                );

                if (! (bool) ($connect?->allowed ?? false)) {
                    $failures[] = "Runtime DB role CONNECT yetkisine sahip değil: {$database}.";
                }

                $create = DB::connection('master')->selectOne(
                    "SELECT has_database_privilege(?, ?, 'CREATE') AS allowed",
                    [$role, $database],
                );

                if ((bool) ($create?->allowed ?? false)) {
                    $failures[] = "Runtime DB role database CREATE yetkisi taşımamalıdır: {$database}.";
                }
            }

            $failures = [
                ...$failures,
                ...$this->schemaCreateFailures($role),
            ];
        } catch (Throwable $exception) {
            $failures[] = 'Runtime DB privilege doğrulaması çalıştırılamadı: '.class_basename($exception);
        }

        return array_values(array_unique($failures));
    }

    /** @return list<string> */
    private function schemaCreateFailures(string $role): array
    {
        $failures = [];
        $original = config('database.connections.period.database');

        try {
            $databases = Period::query()
                ->whereIn('status', ['active', 'closed'])
                ->pluck('database_name')
                ->map(fn ($value): string => (string) $value)
                ->all();

            foreach ($databases as $database) {
                config(['database.connections.period.database' => $database]);
                DB::purge('period');

                $row = DB::connection('period')->selectOne(
                    "SELECT has_schema_privilege(?, 'public', 'CREATE') AS allowed",
                    [$role],
                );

                if ((bool) ($row?->allowed ?? false)) {
                    $failures[] = "Runtime DB role public schema CREATE yetkisi taşımamalıdır: {$database}.";
                }
            }

            $masterSchema = DB::connection('master')->selectOne(
                "SELECT has_schema_privilege(?, 'public', 'CREATE') AS allowed",
                [$role],
            );

            if ((bool) ($masterSchema?->allowed ?? false)) {
                $failures[] = 'Runtime DB role Master public schema CREATE yetkisi taşımamalıdır.';
            }
        } finally {
            config(['database.connections.period.database' => $original]);
            DB::purge('period');
        }

        return $failures;
    }
}
