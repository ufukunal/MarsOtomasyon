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
            WITH movement_totals AS (
                SELECT
                    product_id,
                    location_id,
                    SUM(
                        CASE
                            WHEN direction = 'in' THEN quantity
                            WHEN direction = 'out' THEN -quantity
                            ELSE 0
                        END
                    ) AS quantity
                FROM stock_movements
                GROUP BY product_id, location_id
            )
            SELECT
                COALESCE(b.product_id, m.product_id) AS product_id,
                COALESCE(b.location_id, m.location_id) AS location_id,
                COALESCE(b.quantity, 0)::text AS stored_quantity,
                COALESCE(m.quantity, 0)::text AS calculated_quantity
            FROM stock_balances b
            FULL OUTER JOIN movement_totals m
              ON m.product_id = b.product_id
             AND m.location_id = b.location_id
            ORDER BY 1, 2
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
