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
 * Actual package rows grouped by import file, container and product.
 * No invoice-to-container relationship is assumed.
 */
final class ImportContainerProductReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'imports.container_products',
            title: 'Konteyner Bazlı Ürün Dökümü',
            category: 'İthalat',
            permission: 'import_shipments.view',
            filters: [
                new ReportFilterDefinition('import_number', 'İthalat Dosya No'),
                new ReportFilterDefinition('container_no', 'Konteyner No'),
                new ReportFilterDefinition('product_code', 'Ürün Kodu'),
                new ReportFilterDefinition('status', 'İthalat Durumu', 'select', allowedValues: ['draft', 'in_transit', 'customs', 'received', 'closed']),
            ],
            columns: [
                new ReportColumnDefinition('id', 'Konteyner ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('product_id', 'Ürün ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('import_number', 'İthalat Dosyası'),
                new ReportColumnDefinition('container_no', 'Konteyner No'),
                new ReportColumnDefinition('container_type', 'Konteyner Tipi'),
                new ReportColumnDefinition('container_status', 'Konteyner Durumu'),
                new ReportColumnDefinition('received_at', 'İthalat Teslim Tarihi', 'date'),
                new ReportColumnDefinition('product_code', 'Ürün Kodu'),
                new ReportColumnDefinition('product_name', 'Ürün Adı'),
                new ReportColumnDefinition('unit_name', 'Birim'),
                new ReportColumnDefinition('import_quantity', 'Konteyner Miktarı', 'quantity'),
                new ReportColumnDefinition('package_count', 'Koli Kaydı', 'integer'),
            ],
            defaultSort: [
                new ReportSort('import_number'),
                new ReportSort('container_no'),
                new ReportSort('product_code'),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')->table('packages as pk')
            ->join('containers as ct', 'ct.id', '=', 'pk.container_id')
            ->join('import_files as f', 'f.id', '=', 'pk.import_file_id')
            ->join('products as p', 'p.id', '=', 'pk.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->whereNotNull('pk.product_id');

        if (isset($context->filters['import_number'])) {
            $base->whereRaw('UPPER(f.number) = UPPER(?)', [$context->filters['import_number']]);
        }
        if (isset($context->filters['container_no'])) {
            $base->whereRaw('UPPER(ct.container_no) = UPPER(?)', [$context->filters['container_no']]);
        }
        if (isset($context->filters['product_code'])) {
            $base->whereRaw('UPPER(p.code) = UPPER(?)', [$context->filters['product_code']]);
        }
        if (isset($context->filters['status'])) {
            $base->where('f.status', $context->filters['status']);
        }

        $countQuery = (clone $base)->selectRaw('f.id AS import_id, ct.id AS container_id, p.id AS product_id')
            ->groupBy('f.id', 'ct.id', 'p.id');
        $totalRows = DB::connection('period')->query()->fromSub($countQuery, 'grouped_container_products')->count();

        $rows = (clone $base)->selectRaw(
            'ct.id AS id, p.id AS product_id, f.number AS import_number, '
            .'ct.container_no AS container_no, ct.container_type AS container_type, '
            .'ct.status AS container_status, f.received_at::text AS received_at, '
            .'p.code AS product_code, p.name AS product_name, u.name AS unit_name, '
            .'SUM(pk.quantity)::text AS import_quantity, COUNT(pk.id)::integer AS package_count'
        )->groupBy(
            'f.id', 'f.number', 'f.received_at',
            'ct.id', 'ct.container_no', 'ct.container_type', 'ct.status',
            'p.id', 'p.code', 'p.name', 'u.name',
        );

        $sortMap = [
            'id' => 'ct.id',
            'product_id' => 'p.id',
            'import_number' => 'f.number',
            'container_no' => 'ct.container_no',
            'container_type' => 'ct.container_type',
            'container_status' => 'ct.status',
            'received_at' => 'f.received_at',
            'product_code' => 'p.code',
            'product_name' => 'p.name',
            'unit_name' => 'u.name',
            'import_quantity' => 'SUM(pk.quantity)',
            'package_count' => 'COUNT(pk.id)',
        ];

        foreach ($context->sort as $sort) {
            $rows->orderByRaw($sortMap[$sort->key].' '.$sort->direction);
        }
        $rows->orderBy('f.id')->orderBy('ct.id')->orderBy('p.id');

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)
                ->get()->map(fn (object $row): array => (array) $row)->all(),
            totals: [],
            totalRows: $totalRows,
        );
    }
}
