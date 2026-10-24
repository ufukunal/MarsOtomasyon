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
final class StockCountDifferenceReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'stock.count_differences',
            title: 'Kesinleşmiş Stok Sayım Farkları',
            category: 'Stok / Depo',
            permission: 'stock_counts.view',
            filters: [
                new ReportFilterDefinition('date_from', 'Başlangıç', 'date'),
                new ReportFilterDefinition('date_to', 'Bitiş', 'date'),
                new ReportFilterDefinition('location_id', 'Depo', 'positive_integer'),
                new ReportFilterDefinition('product_id', 'Ürün', 'positive_integer'),
                new ReportFilterDefinition('difference_only', 'Yalnız farklar', 'boolean'),
            ],
            columns: [
                new ReportColumnDefinition('id', 'Satır ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('count_id', 'Sayım ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('number', 'Sayım No', 'string'),
                new ReportColumnDefinition('count_date', 'Sayım Tarihi', 'date'),
                new ReportColumnDefinition('location_name', 'Depo', 'string'),
                new ReportColumnDefinition('product_code', 'Ürün Kodu', 'string'),
                new ReportColumnDefinition('product_name', 'Ürün', 'string'),
                new ReportColumnDefinition('unit_name', 'Birim', 'string'),
                new ReportColumnDefinition('system_quantity', 'Sistem Miktarı', 'quantity'),
                new ReportColumnDefinition('counted_quantity', 'Sayım Miktarı', 'quantity'),
                new ReportColumnDefinition('difference', 'Sayım Farkı', 'quantity'),
                new ReportColumnDefinition('is_approved', 'Onaylı', 'boolean'),
            ],
            defaultSort: [new ReportSort('count_date', 'desc'), new ReportSort('id', 'desc')],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')
            ->table('stock_counts as sc')
            ->join('stock_count_lines as scl', 'scl.stock_count_id', '=', 'sc.id')
            ->join('products as p', 'p.id', '=', 'scl.product_id')
            ->join('locations as l', 'l.id', '=', 'sc.location_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id');
        $base->where('sc.status', 'posted');

        $filters = $context->filters;
        if (isset($filters['date_from'])) {
            $base->whereDate('sc.count_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $base->whereDate('sc.count_date', '<=', $filters['date_to']);
        }
        if (isset($filters['location_id'])) {
            $base->where('sc.location_id', $filters['location_id']);
        }
        if (isset($filters['product_id'])) {
            $base->where('scl.product_id', $filters['product_id']);
        }
        if (($filters['difference_only'] ?? false) === true) {
            $base->where('scl.difference', '<>', 0);
        }

        $totalRows = (clone $base)->count('scl.id');
        $columns = [
            'id' => 'scl.id',
            'count_id' => 'sc.id',
            'number' => 'sc.number',
            'count_date' => 'sc.count_date',
            'location_name' => 'l.name',
            'product_code' => 'p.code',
            'product_name' => 'p.name',
            'unit_name' => 'u.name',
            'system_quantity' => 'scl.system_quantity',
            'counted_quantity' => 'scl.counted_quantity',
            'difference' => 'scl.difference',
            'is_approved' => 'scl.is_approved',
        ];
        $textTypes = ['date', 'quantity', 'decimal'];
        $types = array_column(
            array_map(fn ($column): array => [$column->key, $column->type], $this->definition()->columns),
            1, 0,
        );
        $rows = clone $base;
        if (! $context->hasColumn('id')) {
            $rows->selectRaw('scl.id AS id');
        }
        foreach ($context->columns as $column) {
            $expression = $columns[$column];
            $rows->selectRaw('('.$expression.')'.(in_array($types[$column], $textTypes, true) ? '::text' : '').' AS '.$column);
        }
        foreach ($context->sort as $sort) {
            $rows->orderByRaw($columns[$sort->key].' '.$sort->direction);
        }
        $rows->orderBy('scl.id');

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)->get()
                ->map(fn (object $row): array => (array) $row)->all(),
            totals: [],
            totalRows: $totalRows,
        );
    }
}
