<?php

namespace App\Support\Period;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class PeriodSchemaVersion
{
    /**
     * @return array<int, string>
     */
    public function expectedMigrations(): array
    {
        $files = glob(database_path('migrations/period/*.php')) ?: [];

        $migrations = array_map(
            fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
            $files,
        );

        sort($migrations, SORT_STRING);

        return $migrations;
    }

    /**
     * @return array<int, string>
     */
    public function appliedMigrations(): array
    {
        if (! Schema::connection('period')->hasTable('migrations')) {
            return [];
        }

        return DB::connection('period')
            ->table('migrations')
            ->orderBy('migration')
            ->pluck('migration')
            ->map(fn ($migration): string => (string) $migration)
            ->all();
    }

    /**
     * @return array{expected:string|null,current:string|null,pending:array<int,string>,extra:array<int,string>}
     */
    public function status(): array
    {
        $expected = $this->expectedMigrations();
        $applied = $this->appliedMigrations();

        $pending = array_values(array_diff($expected, $applied));
        $extra = array_values(array_diff($applied, $expected));

        return [
            'expected' => $expected === [] ? null : end($expected),
            'current' => $applied === [] ? null : end($applied),
            'pending' => $pending,
            'extra' => $extra,
        ];
    }

    public function targetVersion(): ?string
    {
        $migrations = $this->expectedMigrations();

        return $migrations === [] ? null : end($migrations);
    }

    public function currentDatabaseVersion(): string
    {
        $status = $this->status();

        if ($status['pending'] !== []) {
            throw new RuntimeException(
                'Period DB migration zinciri tamamlanmadı: '.implode(', ', $status['pending']),
            );
        }

        return $status['current'] ?? 'empty';
    }
}
