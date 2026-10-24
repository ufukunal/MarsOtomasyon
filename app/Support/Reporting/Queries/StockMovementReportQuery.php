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

/** One physical stock movement per row, including the reversing movement. */
final class StockMovementReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'stock.movements',
            title: 'Stok Hareketleri',
            category: 'Stok',
            permission: 'stock.view',
            filters: [
                new ReportFilterDefinition('date_from', 'Başlangıç', 'date'),
                new ReportFilterDefinition('date_to', 'Bitiş', 'date'),
                new ReportFilterDefinition('product_id', 'Ürün', 'positive_integer'),
                new ReportFilterDefinition('location_id', 'Depo', 'positive_integer'),
                new ReportFilterDefinition('direction', 'Giriş / Çıkış', 'select', allowedValues: ['in', 'out']),
                new ReportFilterDefinition('reason', 'Hareket nedeni'),
                new ReportFilterDefinition('document_type', 'Kaynak belge türü'),
            ],
            columns: [
                new ReportColumnDefinition('id', 'Hareket ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('movement_date', 'Tarih', 'date'),
                new ReportColumnDefinition('product_id', 'Ürün ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('product_code', 'Ürün Kodu'),
                new ReportColumnDefinition('product_name', 'Ürün'),
                new ReportColumnDefinition('unit_name', 'Temel Birim'),
                new ReportColumnDefinition('location_code', 'Depo Kodu'),
                new ReportColumnDefinition('location_name', 'Depo'),
                new ReportColumnDefinition('direction', 'Yön'),
                new ReportColumnDefinition('reason', 'Neden'),
                new ReportColumnDefinition('quantity', 'Miktar', 'quantity'),
                new ReportColumnDefinition('balance_after', 'İşlem Sonu Bakiye', 'quantity'),
                new ReportColumnDefinition('document_type', 'Kaynak Türü'),
                new ReportColumnDefinition('document_no', 'Kaynak No'),
                new ReportColumnDefinition('unit_cost', 'Birim Maliyet', 'decimal', costSensitive: true),
                new ReportColumnDefinition('total_cost', 'Hareket Maliyeti', 'decimal', costSensitive: true),
            ],
            defaultSort: [new ReportSort('movement_date', 'desc'), new ReportSort('id', 'desc')],
            drillDowns: [
                new ReportDrillDownDefinition('product', 'Ürün', 'products.view', 'product_id', 'products'),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')->table('stock_movements as sm')
            ->join('products as p', 'p.id', '=', 'sm.product_id')
            ->join('locations as l', 'l.id', '=', 'sm.location_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id');

        $f = $context->filters;
        if (isset($f['date_from'])) {
            $base->whereDate('sm.movement_date', '>=', $f['date_from']);
        }
        if (isset($f['date_to'])) {
            $base->whereDate('sm.movement_date', '<=', $f['date_to']);
        }
        foreach (['product_id', 'location_id', 'direction', 'reason', 'document_type'] as $field) {
            if (isset($f[$field])) {
                $base->where('sm.'.$field, $f[$field]);
            }
        }

        $map = [
            'id' => 'sm.id', 'movement_date' => 'sm.movement_date',
            'product_id' => 'p.id', 'product_code' => 'p.code',
            'product_name' => 'p.name', 'unit_name' => 'u.name',
            'location_code' => 'l.code', 'location_name' => 'l.name',
            'direction' => 'sm.direction', 'reason' => 'sm.reason',
            'quantity' => 'sm.quantity', 'balance_after' => 'sm.balance_after',
            'document_type' => 'sm.document_type', 'document_no' => 'sm.document_no',
            'unit_cost' => 'sm.unit_cost', 'total_cost' => 'sm.total_cost',
        ];
        $dateKeys = ['movement_date'];
        $quantityKeys = ['quantity', 'balance_after', 'unit_cost', 'total_cost'];
        $rows = clone $base;

        if (! $context->hasColumn('id')) {
            $rows->selectRaw('sm.id AS id');
        }
        if (! $context->hasColumn('product_id')) {
            $rows->selectRaw('p.id AS product_id');
        }
        foreach ($context->columns as $column) {
            $expr = $map[$column];
            $rows->selectRaw($expr.(in_array($column, [...$dateKeys, ...$quantityKeys], true) ? '::text' : '').' AS '.$column);
        }
        foreach ($context->sort as $sort) {
            $rows->orderBy($map[$sort->key], $sort->direction);
        }
        $rows->orderBy('sm.id');

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)->get()
                ->map(fn (object $row): array => (array) $row)->all(),
            totals: [], // Quantities across unrelated products/units must never be added.
            totalRows: (clone $base)->count('sm.id'),
        );
    }
}
