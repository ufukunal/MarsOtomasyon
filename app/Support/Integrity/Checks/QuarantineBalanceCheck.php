<?php

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuarantineBalanceCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'quarantine';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('quarantine_entries')
            || ! Schema::connection('period')->hasTable('stock_balances')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable', 'reason' => 'quarantine schema henüz kurulmadı'],
            );
        }

        $rows = DB::connection('period')->select(<<<'SQL'
            WITH quarantine_totals AS (
                SELECT
                    product_id,
                    location_id,
                    SUM(quantity - released_quantity - scrapped_quantity) AS pending
                FROM quarantine_entries
                GROUP BY product_id, location_id
            )
            SELECT
                COALESCE(b.product_id, q.product_id) AS product_id,
                COALESCE(b.location_id, q.location_id) AS location_id,
                COALESCE(b.quarantine, 0)::text AS stored_quarantine,
                COALESCE(q.pending, 0)::text AS calculated_quarantine
            FROM stock_balances b
            FULL OUTER JOIN quarantine_totals q
              ON q.product_id = b.product_id
             AND q.location_id = b.location_id
            ORDER BY 1, 2
        SQL);

        $mismatches = [];

        foreach ($rows as $row) {
            if (bccomp((string) $row->stored_quarantine, (string) $row->calculated_quarantine, 3) !== 0) {
                $mismatches[] = [
                    'product_id' => $row->product_id,
                    'location_id' => $row->location_id,
                    'stored' => $row->stored_quarantine,
                    'calculated' => $row->calculated_quarantine,
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
