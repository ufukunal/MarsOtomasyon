<?php

namespace App\Support\Reporting\Queries;

use App\Contracts\Reporting\ReportQuery;
use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportDefinition;
use App\Support\Reporting\ReportDrillDownDefinition;
use App\Support\Reporting\ReportExecutionContext;
use App\Support\Reporting\ReportFilterDefinition;
use App\Support\Reporting\ReportQueryResult;
use App\Support\Reporting\ReportSort;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Current physical balance with the date of the last recorded movement.
 * This is NOT FIFO stock age or age of an import lot.
 */
final class InactiveStockReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'stock.inactive',
            title: 'Hareketsiz Stok ve Son Hareket',
            category: 'Stok',
            permission: 'stock.view',
            filters: [
                new ReportFilterDefinition('inactive_days', 'Hareketsiz Gün (Varsayılan 90)', 'positive_integer'),
                new ReportFilterDefinition('product_id', 'Ürün', 'positive_integer'),
                new ReportFilterDefinition('location_id', 'Depo', 'positive_integer'),
                new ReportFilterDefinition('active_only', 'Yalnız Aktif Ürün / Depo', 'boolean'),
            ],
            columns: [
                new ReportColumnDefinition('product_id', 'Ürün ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('product_code', 'Ürün Kodu'),
                new ReportColumnDefinition('product_name', 'Ürün'),
                new ReportColumnDefinition('unit_name', 'Temel Birim'),
                new ReportColumnDefinition('location_id', 'Depo ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('location_name', 'Depo'),
                new ReportColumnDefinition('quantity', 'Güncel Fiziksel Stok', 'quantity'),
                new ReportColumnDefinition('reserved', 'Rezerve', 'quantity'),
                new ReportColumnDefinition('quarantine', 'Karantina', 'quantity'),
                new ReportColumnDefinition('available', 'Kullanılabilir', 'quantity'),
                new ReportColumnDefinition('last_movement_date', 'Son Stok Hareketi', 'date'),
                new ReportColumnDefinition('inactive_days', 'Hareketsiz Gün', 'integer'),
                new ReportColumnDefinition('activity_status', 'Durum', sortable: false),
            ],
            defaultSort: [new ReportSort('inactive_days', 'desc'), new ReportSort('product_code')],
            drillDowns: [
                new ReportDrillDownDefinition('product', 'Ürün', 'products.view', 'product_id', 'products'),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $days = (int) ($context->filters['inactive_days'] ?? 90);
        if ($days < 1 || $days > 3650) {
            throw new DomainException('Hareketsiz stok gün sınırı 1–3650 olmalıdır.');
        }

        $today = CarbonImmutable::today(config('app.timezone'));
        $asOf = $today->toDateString();
        $cutoff = $today->subDays($days)->toDateString();
        $db = DB::connection('period');

        $lastMovement = $db->table('stock_movements')
            ->where('movement_date', '<=', $asOf)
            ->groupBy('product_id', 'location_id')
            ->selectRaw('product_id, location_id, MAX(movement_date) AS last_movement_date');

        $base = $db->table('stock_balances as sb')
            ->join('products as p', 'p.id', '=', 'sb.product_id')
            ->join('locations as l', 'l.id', '=', 'sb.location_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->leftJoinSub($lastMovement, 'last_move', function ($join): void {
                $join->on('last_move.product_id', '=', 'sb.product_id')
                    ->on('last_move.location_id', '=', 'sb.location_id');
            })
            ->where('sb.quantity', '>', 0)
            ->where(function (Builder $query) use ($cutoff): void {
                $query->whereNull('last_move.last_movement_date')
                    ->orWhere('last_move.last_movement_date', '<=', $cutoff);
            });

        $f = $context->filters;
        if (isset($f['product_id'])) {
            $base->where('sb.product_id', $f['product_id']);
        }
        if (isset($f['location_id'])) {
            $base->where('sb.location_id', $f['location_id']);
        }
        if (($f['active_only'] ?? false) === true) {
            $base->where('p.is_active', true)->where('l.is_active', true);
        }

        $daysSql = '(?::date - last_move.last_movement_date)';
        $fields = [
            'product_id' => 'p.id',
            'product_code' => 'p.code',
            'product_name' => 'p.name',
            'unit_name' => 'u.name',
            'location_id' => 'l.id',
            'location_name' => 'l.name',
            'quantity' => 'sb.quantity',
            'reserved' => 'sb.reserved',
            'quarantine' => 'sb.quarantine',
            'available' => '(sb.quantity - sb.reserved - sb.consignment_reserved - sb.quarantine)',
            'last_movement_date' => 'last_move.last_movement_date',
            'inactive_days' => $daysSql,
        ];
        $numeric = ['quantity','reserved','quarantine','available'];
        $rows = clone $base;

        foreach (['product_id' => 'p.id', 'location_id' => 'l.id'] as $key => $expr) {
            if (! $context->hasColumn($key)) {
                $rows->selectRaw($expr.' AS '.$key);
            }
        }
        foreach ($context->columns as $key) {
            if ($key === 'activity_status') {
                $rows->selectRaw("(CASE WHEN last_move.last_movement_date IS NULL
                    THEN 'Hareket kaydı yok' ELSE 'Hareketsiz' END) AS activity_status");
                continue;
            }
            $expr = $fields[$key];
            $sql = $expr.(in_array($key, [...$numeric,'last_movement_date'], true) ? '::text' : '');
            $rows->selectRaw($sql.' AS '.$key, $key === 'inactive_days' ? [$asOf] : []);
        }

        foreach ($context->sort as $sort) {
            if ($sort->key === 'inactive_days') {
                $rows->orderByRaw($daysSql.' '.$sort->direction.' NULLS LAST', [$asOf]);
            } else {
                $rows->orderBy($fields[$sort->key], $sort->direction);
            }
        }
        $rows->orderBy('sb.product_id')->orderBy('sb.location_id');

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)
                ->get()->map(fn (object $row): array => (array) $row)->all(),
            totals: [],
            totalRows: (clone $base)->count('sb.id'),
        );
    }
}
