<?php

namespace App\Support\Stock;

use App\Models\Period\StockBalance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class StockStatusQuery
{
    /** @return Builder<StockBalance> */
    public function build(bool $includeCosts): Builder
    {
        $available = '(sb.quantity - sb.reserved - sb.consignment_reserved - sb.quarantine)';

        $physical = DB::connection('period')
            ->table('stock_balances as sb')
            ->join('products as p', 'p.id', '=', 'sb.product_id')
            ->join('locations as l', 'l.id', '=', 'sb.location_id');

        if ($includeCosts) {
            $physical->leftJoin('product_costs as pc', 'pc.product_id', '=', 'p.id');
        }

        $physical->select([
            'sb.id',
            'sb.product_id',
            'sb.location_id',
            'sb.quantity',
            'sb.reserved',
            'sb.consignment_reserved',
            'sb.quarantine',
            'sb.created_at',
            'sb.updated_at',
            DB::raw("p.code || ' · ' || p.name AS product_label"),
            DB::raw('p.code AS product_code'),
            DB::raw('p.name AS product_name'),
            DB::raw('l.name AS location_name'),
            'p.category_id',
            'p.brand_id',
            'p.min_stock',
            DB::raw('p.kind AS product_kind'),
            DB::raw("{$available}::text AS available"),
            DB::raw(
                "CASE
                    WHEN {$available} <= 0 THEN 'Tükendi'
                    WHEN {$available} <= p.min_stock THEN 'Kritik'
                    ELSE 'Yeterli'
                 END AS stock_status"
            ),
        ]);

        if ($includeCosts) {
            $physical->addSelect([
                DB::raw('COALESCE(pc.moving_average, 0)::text AS moving_average'),
                DB::raw('trunc(sb.quantity * COALESCE(pc.moving_average, 0), 4)::text AS stock_value'),
            ]);
        }

        $componentAvailable = <<<'SQL'
            (
                SELECT COALESCE(
                    MIN(
                        FLOOR(
                            COALESCE(
                                (
                                    SELECT SUM(
                                        sb2.quantity
                                        - sb2.reserved
                                        - sb2.consignment_reserved
                                        - sb2.quarantine
                                    )
                                    FROM stock_balances sb2
                                    WHERE sb2.product_id = ps.component_product_id
                                      AND sb2.location_id = l.id
                                ),
                                0
                            ) / NULLIF(ps.quantity, 0)
                        )
                    ),
                    0
                )
                FROM product_sets ps
                WHERE ps.set_product_id = p.id
            )
        SQL;

        $sets = DB::connection('period')
            ->table('products as p')
            ->crossJoin('locations as l')
            ->where('p.kind', 'set')
            ->select([
                DB::raw('-(p.id * 1000000000 + l.id)::bigint AS id'),
                DB::raw('p.id AS product_id'),
                DB::raw('l.id AS location_id'),
                DB::raw('0::numeric(18,3) AS quantity'),
                DB::raw('0::numeric(18,3) AS reserved'),
                DB::raw('0::numeric(18,3) AS consignment_reserved'),
                DB::raw('0::numeric(18,3) AS quarantine'),
                DB::raw('NULL::timestamp AS created_at'),
                DB::raw('NULL::timestamp AS updated_at'),
                DB::raw("p.code || ' · ' || p.name AS product_label"),
                DB::raw('p.code AS product_code'),
                DB::raw('p.name AS product_name'),
                DB::raw('l.name AS location_name'),
                'p.category_id',
                'p.brand_id',
                'p.min_stock',
                DB::raw('p.kind AS product_kind'),
                DB::raw("({$componentAvailable})::text AS available"),
                DB::raw(
                    "CASE
                        WHEN ({$componentAvailable}) <= 0 THEN 'Tükendi'
                        WHEN ({$componentAvailable}) <= p.min_stock THEN 'Kritik'
                        ELSE 'Yeterli'
                     END AS stock_status"
                ),
            ]);

        if ($includeCosts) {
            $sets->addSelect([
                DB::raw('NULL::text AS moving_average'),
                DB::raw('NULL::text AS stock_value'),
            ]);
        }

        $union = $physical->unionAll($sets);

        return StockBalance::query()->fromSub($union, 'stock_balances');
    }
}
