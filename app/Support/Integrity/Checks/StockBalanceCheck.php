<?php

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockBalanceCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'stock';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('stock_movements')
            || ! Schema::connection('period')->hasTable('stock_balances')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable', 'reason' => 'stock schema henüz kurulmadı'],
            );
        }

        $rows = DB::connection('period')->select(<<<'SQL'
            SELECT
                b.product_id,
                b.location_id,
                b.quantity::text AS stored_quantity,
                COALESCE(SUM(
                    CASE
                        WHEN m.direction = 'in' THEN m.quantity
                        WHEN m.direction = 'out' THEN -m.quantity
                        ELSE 0
                    END
                ), 0)::text AS calculated_quantity
            FROM stock_balances b
            LEFT JOIN stock_movements m
                ON m.product_id = b.product_id
               AND m.location_id = b.location_id
            GROUP BY b.product_id, b.location_id, b.quantity
            ORDER BY b.product_id, b.location_id
        SQL);

        $mismatches = [];

        foreach ($rows as $row) {
            if (bccomp((string) $row->stored_quantity, (string) $row->calculated_quantity, 3) !== 0) {
                $mismatches[] = [
                    'product_id' => $row->product_id,
                    'location_id' => $row->location_id,
                    'stored' => $row->stored_quantity,
                    'calculated' => $row->calculated_quantity,
                ];
            }
        }

        return new IntegrityResult(
            checked: count($rows),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
