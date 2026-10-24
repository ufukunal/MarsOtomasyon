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
final class StockTransferReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'stock.transfers',
            title: 'Depo Transferleri ve Teslim Miktarları',
            category: 'Stok / Depo',
            permission: 'transfers.view',
            filters: [
                new ReportFilterDefinition('date_from', 'Başlangıç', 'date'),
                new ReportFilterDefinition('date_to', 'Bitiş', 'date'),
                new ReportFilterDefinition('from_location_id', 'Çıkış Deposu', 'positive_integer'),
                new ReportFilterDefinition('to_location_id', 'Varış Deposu', 'positive_integer'),
                new ReportFilterDefinition('product_id', 'Ürün', 'positive_integer'),
                new ReportFilterDefinition('status', 'Durum', 'string'),
            ],
            columns: [
                new ReportColumnDefinition('id', 'Satır ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('transfer_id', 'Transfer ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('number', 'Transfer No', 'string'),
                new ReportColumnDefinition('transfer_date', 'Tarih', 'date'),
                new ReportColumnDefinition('status', 'Durum', 'string'),
                new ReportColumnDefinition('source_location', 'Gönderen Depo', 'string'),
                new ReportColumnDefinition('target_location', 'Alan Depo', 'string'),
                new ReportColumnDefinition('product_code', 'Ürün Kodu', 'string'),
                new ReportColumnDefinition('product_name', 'Ürün', 'string'),
                new ReportColumnDefinition('unit_name', 'Birim', 'string'),
                new ReportColumnDefinition('quantity', 'Gönderilen', 'quantity'),
                new ReportColumnDefinition('received_quantity', 'Teslim Alınan', 'quantity'),
                new ReportColumnDefinition('pending_quantity', 'Bekleyen', 'quantity'),
            ],
            defaultSort: [new ReportSort('transfer_date', 'desc'), new ReportSort('id', 'desc')],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')
            ->table('transfers as t')
            ->join('transfer_lines as tl', 'tl.transfer_id', '=', 't.id')
            ->join('products as p', 'p.id', '=', 'tl.product_id')
            ->join('locations as src', 'src.id', '=', 't.from_location_id')
            ->join('locations as dst', 'dst.id', '=', 't.to_location_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id');
        

        $filters = $context->filters;
        if (isset($filters['date_from'])) {
            $base->whereDate('t.transfer_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $base->whereDate('t.transfer_date', '<=', $filters['date_to']);
        }
        if (isset($filters['from_location_id'])) {
            $base->where('t.from_location_id', $filters['from_location_id']);
        }
        if (isset($filters['to_location_id'])) {
            $base->where('t.to_location_id', $filters['to_location_id']);
        }
        if (isset($filters['product_id'])) {
            $base->where('tl.product_id', $filters['product_id']);
        }
        if (isset($filters['status'])) {
            $base->where('t.status', $filters['status']);
        }

        $totalRows = (clone $base)->count('tl.id');
        $columns = [
            'id' => 'tl.id',
            'transfer_id' => 't.id',
            'number' => 't.number',
            'transfer_date' => 't.transfer_date',
            'status' => 't.status',
            'source_location' => 'src.name',
            'target_location' => 'dst.name',
            'product_code' => 'p.code',
            'product_name' => 'p.name',
            'unit_name' => 'u.name',
            'quantity' => 'tl.quantity',
            'received_quantity' => 'tl.received_quantity',
            'pending_quantity' => '(tl.quantity - tl.received_quantity)',
        ];
        $textTypes = ['date', 'quantity', 'decimal'];
        $types = array_column(
            array_map(fn ($column): array => [$column->key, $column->type], $this->definition()->columns),
            1, 0,
        );
        $rows = clone $base;
        if (! $context->hasColumn('id')) {
            $rows->selectRaw('tl.id AS id');
        }
        foreach ($context->columns as $column) {
            $expression = $columns[$column];
            $rows->selectRaw('('.$expression.')'.(in_array($types[$column], $textTypes, true) ? '::text' : '').' AS '.$column);
        }
        foreach ($context->sort as $sort) {
            $rows->orderByRaw($columns[$sort->key].' '.$sort->direction);
        }
        $rows->orderBy('tl.id');

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)->get()
                ->map(fn (object $row): array => (array) $row)->all(),
            totals: [],
            totalRows: $totalRows,
        );
    }
}
