<?php

namespace App\Support\PeriodCarry;

use DomainException;
use Illuminate\Support\Facades\DB;

final class PeriodCarryTableCopier
{
    /**
     * @param  list<int>  $ids
     * @param  list<string>  $identityColumns
     */
    public function copyIds(string $table, array $ids, array $identityColumns = []): int
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, fn ($id): bool => (int) $id > 0))));

        if ($ids === []) {
            return 0;
        }

        $sourceRows = DB::connection('period_source')
            ->table($table)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(fn (object $row): int => (int) $row->id);

        if ($sourceRows->count() !== count($ids)) {
            $found = $sourceRows->keys()->map(fn ($id): int => (int) $id)->all();

            throw new DomainException(
                'Carry source '.$table.' eksik ID içeriyor: '.implode(', ', array_diff($ids, $found)),
            );
        }

        foreach ($ids as $id) {
            $row = $sourceRows->get($id);

            if (! $row) {
                throw new DomainException("Carry source {$table} #{$id} bulunamadı.");
            }

            $values = (array) $row;

            foreach ($identityColumns as $column) {
                $value = $values[$column] ?? null;

                if ($value === null) {
                    continue;
                }

                $conflict = DB::connection('period')->table($table)
                    ->where($column, $value)
                    ->where('id', '<>', $id)
                    ->exists();

                if ($conflict) {
                    throw new DomainException(
                        "Target {$table}.{$column}={$value} farklı ID ile mevcut; ID sürekliliği korunamaz.",
                    );
                }
            }

            if (DB::connection('period')->table($table)->where('id', $id)->exists()) {
                $update = $values;
                unset($update['id']);
                DB::connection('period')->table($table)->where('id', $id)->update($update);
            } else {
                DB::connection('period')->table($table)->insert($values);
            }
        }

        $this->resetSequence($table);

        return $sourceRows->count();
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $keyColumns
     */
    public function copyPivot(string $table, array $rows, array $keyColumns): int
    {
        $copied = 0;

        foreach ($rows as $row) {
            $lookup = [];

            foreach ($keyColumns as $column) {
                $lookup[$column] = $row[$column] ?? null;
            }

            $existing = DB::connection('period')->table($table);

            foreach ($lookup as $column => $value) {
                $existing->where($column, $value);
            }

            if (! $existing->exists()) {
                DB::connection('period')->table($table)->insert($row);
                $copied++;
            }
        }

        return $copied;
    }

    public function resetSequence(string $table): void
    {
        $this->assertIdentifier($table);

        $sequence = DB::connection('period')->selectOne(
            "SELECT pg_get_serial_sequence(?, 'id') AS sequence_name",
            [$table],
        )?->sequence_name;

        if ($sequence === null) {
            return;
        }

        DB::connection('period')->statement(sprintf(
            "SELECT setval(
                pg_get_serial_sequence('%1\$s', 'id'),
                COALESCE((SELECT MAX(id) FROM %1\$s), 1),
                (SELECT MAX(id) IS NOT NULL FROM %1\$s)
            )",
            $table,
        ));
    }

    public function assertSequence(string $table): void
    {
        $this->assertIdentifier($table);

        $sequence = DB::connection('period')->selectOne(
            "SELECT pg_get_serial_sequence(?, 'id') AS sequence_name",
            [$table],
        )?->sequence_name;

        if ($sequence === null) {
            return;
        }

        if (! is_string($sequence) || ! preg_match('/^[a-zA-Z0-9_.]+$/', $sequence)) {
            throw new DomainException("{$table} için serial sequence adı geçersiz.");
        }

        $max = (int) (DB::connection('period')->table($table)->max('id') ?? 0);
        $state = DB::connection('period')->selectOne(
            'SELECT last_value, is_called FROM '.$sequence,
        );

        if (! $state) {
            throw new DomainException("{$table} sequence durumu okunamadı.");
        }

        $next = $state->is_called ? ((int) $state->last_value + 1) : (int) $state->last_value;
        $expected = $max + 1;

        if ($next !== $expected) {
            throw new DomainException(
                "{$table} sequence beklenen {$expected}, gerçek {$next}.",
            );
        }
    }

    private function assertIdentifier(string $table): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $table)) {
            throw new DomainException('Geçersiz carry tablo adı.');
        }
    }
}
