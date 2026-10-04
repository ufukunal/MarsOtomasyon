<?php

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReservationBalanceCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'reservations';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('stock_reservations')
            || ! Schema::connection('period')->hasTable('stock_balances')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable', 'reason' => 'reservation schema henüz kurulmadı'],
            );
        }

        $rows = DB::connection('period')->select(<<<'SQL'
            WITH reservation_totals AS (
                SELECT product_id, location_id, SUM(quantity) AS reserved
                FROM stock_reservations
                WHERE status = 'active'
                GROUP BY product_id, location_id
            )
            SELECT
                COALESCE(b.product_id, r.product_id) AS product_id,
                COALESCE(b.location_id, r.location_id) AS location_id,
                COALESCE(b.reserved, 0)::text AS stored_reserved,
                COALESCE(r.reserved, 0)::text AS calculated_reserved
            FROM stock_balances b
            FULL OUTER JOIN reservation_totals r
              ON r.product_id = b.product_id
             AND r.location_id = b.location_id
            ORDER BY 1, 2
        SQL);

        $mismatches = [];

        foreach ($rows as $row) {
            if (bccomp((string) $row->stored_reserved, (string) $row->calculated_reserved, 3) !== 0) {
                $mismatches[] = [
                    'product_id' => $row->product_id,
                    'location_id' => $row->location_id,
                    'stored' => $row->stored_reserved,
                    'calculated' => $row->calculated_reserved,
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
