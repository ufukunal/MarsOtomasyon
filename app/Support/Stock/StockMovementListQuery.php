<?php

namespace App\Support\Stock;

use App\Models\Period\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class StockMovementListQuery
{
    /** @return Builder<StockMovement> */
    public function build(bool $includeCosts): Builder
    {
        $inner = DB::connection('period')
            ->table('stock_movements as sm')
            ->join('products as p', 'p.id', '=', 'sm.product_id')
            ->join('locations as l', 'l.id', '=', 'sm.location_id')
            ->select([
                'sm.id',
                'sm.product_id',
                'sm.location_id',
                'sm.product_code',
                'sm.movement_date',
                'sm.direction',
                'sm.reason',
                'sm.quantity',
                'sm.balance_after',
                'sm.document_type',
                'sm.document_id',
                'sm.document_no',
                'sm.note',
                'sm.created_by',
                'sm.created_by_name',
                'sm.created_at',
                'sm.updated_at',
                DB::raw("sm.product_code || ' · ' || p.name AS product_label"),
                DB::raw('l.name AS location_name'),
                DB::raw(
                    "CASE sm.direction
                        WHEN 'in' THEN 'Giriş'
                        WHEN 'out' THEN 'Çıkış'
                        ELSE sm.direction
                     END AS direction_label"
                ),
                DB::raw(
                    "CASE sm.reason
                        WHEN 'purchase' THEN 'Alış'
                        WHEN 'sale' THEN 'Satış'
                        WHEN 'transfer' THEN 'Transfer'
                        WHEN 'count' THEN 'Sayım'
                        WHEN 'production' THEN 'Üretim'
                        WHEN 'production_consumption' THEN 'Üretim Tüketimi'
                        WHEN 'return' THEN 'İade'
                        WHEN 'sales_return' THEN 'Satış İadesi'
                        WHEN 'purchase_return' THEN 'Alış İadesi'
                        WHEN 'scrap' THEN 'Hurda'
                        WHEN 'opening' THEN 'Açılış'
                        WHEN 'adjustment' THEN 'Düzeltme'
                        ELSE sm.reason
                     END AS reason_label"
                ),
            ]);

        if ($includeCosts) {
            $inner->addSelect([
                'sm.unit_cost',
                'sm.total_cost',
                'sm.avg_cost_after',
            ]);
        }

        return StockMovement::query()->fromSub($inner, 'stock_movements');
    }
}
