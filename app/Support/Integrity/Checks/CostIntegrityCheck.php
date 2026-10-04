<?php

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CostIntegrityCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'costs';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('product_costs')
            || ! Schema::connection('period')->hasTable('stock_movements')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable', 'reason' => 'cost schema henüz kurulmadı'],
            );
        }

        $rows = DB::connection('period')->select(<<<'SQL'
            WITH latest_movement AS (
                SELECT DISTINCT ON (product_id)
                    product_id,
                    avg_cost_after
                FROM stock_movements
                ORDER BY product_id, id DESC
            )
            SELECT
                COALESCE(c.product_id, m.product_id) AS product_id,
                COALESCE(c.moving_average, 0)::text AS stored_average,
                COALESCE(m.avg_cost_after, 0)::text AS movement_average
            FROM product_costs c
            FULL OUTER JOIN latest_movement m ON m.product_id = c.product_id
            ORDER BY 1
        SQL);

        $mismatches = [];

        foreach ($rows as $row) {
            if (bccomp((string) $row->stored_average, (string) $row->movement_average, 4) !== 0) {
                $mismatches[] = [
                    'product_id' => $row->product_id,
                    'stored' => $row->stored_average,
                    'calculated' => $row->movement_average,
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
