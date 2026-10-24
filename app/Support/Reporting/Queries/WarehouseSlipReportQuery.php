<?php

namespace App\Support\Reporting\Queries;

use App\Contracts\Reporting\ReportQuery;
use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportDefinition;
use App\Support\Reporting\ReportExecutionContext;
use App\Support\Reporting\ReportFilterDefinition;
use App\Support\Reporting\ReportQueryResult;
use App\Support\Reporting\ReportSort;
use Illuminate\Support\Facades\DB;

/**
 * Detailed operational rows. No total of quantities across mixed products or units.
 */
final class WarehouseSlipReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'stock.warehouse_slips',
            title: 'Stok Düzeltme Fişleri',
            category: 'Stok / Depo',
            permission: 'warehouse_slips.view',
            filters: [
                new ReportFilterDefinition('date_from', 'Başlangıç', 'date'),
                new ReportFilterDefinition('date_to', 'Bitiş', 'date'),
                new ReportFilterDefinition('location_id', 'Depo', 'positive_integer'),
                new ReportFilterDefinition('product_id', 'Ürün', 'positive_integer'),
                new ReportFilterDefinition('direction', 'Giriş / Çıkış', 'select', allowedValues: ['in', 'out']),
                new ReportFilterDefinition('reason', 'Neden', 'string'),
                new ReportFilterDefinition('status', 'Durum', 'select', allowedValues: ['draft', 'posted', 'cancelled']),
            ],
            columns: [
                new ReportColumnDefinition('id', 'Satır ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('slip_id', 'Fiş ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('number', 'Fiş No', 'string'),
                new ReportColumnDefinition('slip_date', 'Tarih', 'date'),
                new ReportColumnDefinition('location_name', 'Depo', 'string'),
                new ReportColumnDefinition('direction', 'Yön', 'string'),
                new ReportColumnDefinition('reason', 'Neden', 'string'),
                new ReportColumnDefinition('status', 'Durum', 'string'),
                new ReportColumnDefinition('product_code', 'Ürün Kodu', 'string'),
                new ReportColumnDefinition('product_name', 'Ürün', 'string'),
                new ReportColumnDefinition('unit_name', 'Birim', 'string'),
                new ReportColumnDefinition('quantity', 'Fiş Miktarı (İşlem Durumu Ayrı)', 'quantity'),
            ],
            defaultSort: [new ReportSort('slip_date', 'desc'), new ReportSort('id', 'desc')],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')
            ->table('warehouse_slips as ws')
            ->join('warehouse_slip_lines as wsl', 'wsl.warehouse_slip_id', '=', 'ws.id')
            ->join('products as p', 'p.id', '=', 'wsl.product_id')
            ->join('locations as l', 'l.id', '=', 'ws.location_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id');
        

        $filters = $context->filters;
        if (isset($filters['date_from'])) {
            $base->whereDate('ws.slip_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $base->whereDate('ws.slip_date', '<=', $filters['date_to']);
        }
        if (isset($filters['location_id'])) {
            $base->where('ws.location_id', $filters['location_id']);
        }
        if (isset($filters['product_id'])) {
            $base->where('wsl.product_id', $filters['product_id']);
        }
        if (isset($filters['direction'])) {
            $base->where('ws.direction', $filters['direction']);
        }
        if (isset($filters['reason'])) {
            $base->where('ws.reason', $filters['reason']);
        }
        if (isset($filters['status'])) {
            $base->where('ws.status', $filters['status']);
        }

        $totalRows = (clone $base)->count('wsl.id');
        $columns = [
            'id' => 'wsl.id',
            'slip_id' => 'ws.id',
            'number' => 'ws.number',
            'slip_date' => 'ws.slip_date',
            'location_name' => 'l.name',
            'direction' => 'ws.direction',
            'reason' => 'ws.reason',
            'status' => 'ws.status',
            'product_code' => 'p.code',
            'product_name' => 'p.name',
            'unit_name' => 'u.name',
            'quantity' => 'wsl.quantity',
        ];
        $textTypes = ['date', 'quantity', 'decimal'];
        $types = array_column(
            array_map(fn ($column): array => [$column->key, $column->type], $this->definition()->columns),
            1, 0,
        );
        $rows = clone $base;
        if (! $context->hasColumn('id')) {
            $rows->selectRaw('wsl.id AS id');
        }
        foreach ($context->columns as $column) {
            $expression = $columns[$column];
            $rows->selectRaw('('.$expression.')'.(in_array($types[$column], $textTypes, true) ? '::text' : '').' AS '.$column);
        }
        foreach ($context->sort as $sort) {
            $rows->orderByRaw($columns[$sort->key].' '.$sort->direction);
        }
        $rows->orderBy('wsl.id');

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)->get()
                ->map(fn (object $row): array => (array) $row)->all(),
            totals: [],
            totalRows: $totalRows,
        );
    }
}
