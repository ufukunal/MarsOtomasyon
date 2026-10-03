<?php

namespace App\Support\Search;

use Illuminate\Support\Facades\DB;

final class SearchIndexSchema
{
    public static function createTrigramIndex(
        string $table,
        string $column = 'search_index',
        ?string $indexName = null,
    ): void {
        $indexName ??= "{$table}_{$column}_trgm";

        self::assertIdentifier($table);
        self::assertIdentifier($column);
        self::assertIdentifier($indexName);

        DB::connection('period')->statement(
            sprintf(
                'CREATE INDEX %s ON %s USING gin (%s gin_trgm_ops)',
                $indexName,
                $table,
                $column,
            ),
        );
    }

    public static function dropIndex(string $indexName): void
    {
        self::assertIdentifier($indexName);

        DB::connection('period')->statement(
            sprintf('DROP INDEX IF EXISTS %s', $indexName),
        );
    }

    private static function assertIdentifier(string $value): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/D', $value)) {
            throw new \InvalidArgumentException('Geçersiz PostgreSQL identifier.');
        }
    }
}
