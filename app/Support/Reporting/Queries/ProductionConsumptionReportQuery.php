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
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Component-level production use and scrap, excluding reversed completions. */
final class ProductionConsumptionReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'production.consumption',
            title: 'Üretim Malzeme Tüketimi ve Fire',
            category: 'Üretim / Fason',
            permission: 'production_orders.view',
            filters: [
                new ReportFilterDefinition('date_from', 'Başlangıç', 'date'),
                new ReportFilterDefinition('date_to', 'Bitiş', 'date'),
                new ReportFilterDefinition('production_order_id', 'Üretim Emri', 'positive_integer'),
                new ReportFilterDefinition('component_product_id', 'Bileşen Ürün', 'positive_integer'),
                new ReportFilterDefinition('production_type', 'Üretim Türü', 'select', allowedValues: ['internal','subcontract']),
            ],
            columns: [
                new ReportColumnDefinition('id', 'Tüketim ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('production_order_id', 'Üretim Emri ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('completion_date', 'Tamamlama Tarihi', 'date'),
                new ReportColumnDefinition('order_number', 'Emir No'),
                new ReportColumnDefinition('production_type', 'Üretim Türü'),
                new ReportColumnDefinition('output_product_code', 'Üretilen Ürün'),
                new ReportColumnDefinition('component_code', 'Bileşen Kodu'),
                new ReportColumnDefinition('component_name', 'Bileşen'),
                new ReportColumnDefinition('unit_name', 'Temel Birim'),
                new ReportColumnDefinition('consumed_quantity', 'Normal Tüketim', 'quantity'),
                new ReportColumnDefinition('fire_quantity', 'Fire Miktarı', 'quantity'),
                new ReportColumnDefinition('total_quantity', 'Toplam Çıkış', 'quantity'),
                new ReportColumnDefinition('fire_rate', 'Bileşen Fire Payı (%)', 'decimal'),
                new ReportColumnDefinition('unit_cost', 'Birim Maliyet', 'decimal', costSensitive: true),
                new ReportColumnDefinition('total_cost', 'Toplam Tüketim Maliyeti', 'decimal', costSensitive: true),
            ],
            defaultSort: [new ReportSort('completion_date', 'desc'), new ReportSort('id', 'desc')],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')->table('production_consumptions as pc')
            ->join('production_completions as completion', 'completion.id', '=', 'pc.production_completion_id')
            ->join('production_orders as o', 'o.id', '=', 'completion.production_order_id')
            ->join('products as cp', 'cp.id', '=', 'pc.component_product_id')
            ->join('products as output', 'output.id', '=', 'o.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'cp.unit_id')
            ->whereNull('completion.reversal_of_id')
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('production_completions as reversal')
                    ->whereColumn('reversal.reversal_of_id', 'completion.id');
            });

        $f = $context->filters;
        if (isset($f['date_from'])) {
            $base->whereDate('completion.completion_date', '>=', $f['date_from']);
        }
        if (isset($f['date_to'])) {
            $base->whereDate('completion.completion_date', '<=', $f['date_to']);
        }
        if (isset($f['production_order_id'])) {
            $base->where('completion.production_order_id', $f['production_order_id']);
        }
        if (isset($f['component_product_id'])) {
            $base->where('pc.component_product_id', $f['component_product_id']);
        }
        if (isset($f['production_type'])) {
            $base->where('o.production_type', $f['production_type']);
        }

        $qty = '(pc.consumed_quantity + pc.fire_quantity)';
        $rate = "(CASE WHEN $qty > 0 THEN ROUND(pc.fire_quantity * 100 / $qty, 2) ELSE NULL END)";
        $fields = [
            'id' => 'pc.id',
            'production_order_id' => 'o.id',
            'completion_date' => 'completion.completion_date',
            'order_number' => 'o.number',
            'production_type' => 'o.production_type',
            'output_product_code' => 'output.code',
            'component_code' => 'cp.code',
            'component_name' => 'cp.name',
            'unit_name' => 'u.name',
            'consumed_quantity' => 'pc.consumed_quantity',
            'fire_quantity' => 'pc.fire_quantity',
            'total_quantity' => $qty,
            'fire_rate' => $rate,
            'unit_cost' => 'pc.unit_cost',
            'total_cost' => 'pc.total_cost',
        ];
        $numeric = ['consumed_quantity', 'fire_quantity', 'total_quantity', 'fire_rate', 'unit_cost', 'total_cost'];
        $rows = clone $base;
        if (! $context->hasColumn('id')) {
            $rows->selectRaw('pc.id AS id');
        }
        foreach ($context->columns as $col) {
            $rows->selectRaw('('.$fields[$col].')'.(in_array($col, $numeric, true)
                || $col === 'completion_date' ? '::text' : '').' AS '.$col);
        }
        foreach ($context->sort as $sort) {
            $rows->orderByRaw($fields[$sort->key].' '.$sort->direction);
        }
        $rows->orderBy('pc.id');

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)
                ->get()->map(fn (object $row): array => (array) $row)->all(),
            totals: [],
            totalRows: (clone $base)->count('pc.id'),
        );
    }
}
