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

        $events = [
            <<<'SQL'
                SELECT
                    product_id,
                    avg_cost_after AS event_average,
                    created_at AS occurred_at,
                    3 AS source_order,
                    id AS event_id
                FROM stock_movements
            SQL,
        ];

        if (Schema::connection('period')->hasTable('purchase_matches')) {
            $events[] = <<<'SQL'
                SELECT
                    pm.product_id,
                    pm.new_moving_average AS event_average,
                    pm.updated_at AS occurred_at,
                    2 AS source_order,
                    pm.id AS event_id
                FROM purchase_matches pm
                JOIN document_lines dl ON dl.id = pm.supplier_invoice_line_id
                JOIN documents d ON d.id = dl.document_id
                WHERE pm.product_id IS NOT NULL
                  AND pm.new_moving_average IS NOT NULL
                  AND d.status = 'posted'
                  AND NOT EXISTS (
                      SELECT 1
                      FROM document_relations dr
                      WHERE dr.relation_type = 'reversal_of'
                        AND dr.target_document_id = d.id
                  )
            SQL;
        }

        if (Schema::connection('period')->hasTable('inventory_cost_adjustments')) {
            $events[] = <<<'SQL'
                SELECT
                    product_id,
                    moving_average_after AS event_average,
                    created_at AS occurred_at,
                    1 AS source_order,
                    id AS event_id
                FROM inventory_cost_adjustments
            SQL;
        }

        $eventSql = implode("\nUNION ALL\n", $events);
        $sql = <<<SQL
            WITH cost_events AS (
                {$eventSql}
            ),
            latest_event AS (
                SELECT DISTINCT ON (product_id)
                    product_id,
                    event_average
                FROM cost_events
                ORDER BY product_id, occurred_at DESC, source_order DESC, event_id DESC
            )
            SELECT
                c.product_id,
                c.moving_average::text AS stored_average,
                COALESCE(e.event_average, 0)::text AS event_average
            FROM product_costs c
            LEFT JOIN latest_event e ON e.product_id = c.product_id
            ORDER BY c.product_id
        SQL;

        $rows = DB::connection('period')->select($sql);
        $mismatches = [];

        foreach ($rows as $row) {
            if (bccomp((string) $row->stored_average, (string) $row->event_average, 4) !== 0) {
                $mismatches[] = [
                    'product_id' => $row->product_id,
                    'stored' => $row->stored_average,
                    'calculated' => $row->event_average,
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
