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
 * Real package quantities grouped by import file and product.
 * Does not infer invoice origin or allocate revenue across import files.
 */
final class ImportProductReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'imports.products',
            title: 'İthalat Bazlı Ürün Dökümü',
            category: 'İthalat',
            permission: 'import_shipments.view',
            filters: [
                new ReportFilterDefinition('import_number', 'İthalat Dosya No'),
                new ReportFilterDefinition('supplier_code', 'Tedarikçi Kod'),
                new ReportFilterDefinition('product_code', 'Ürün Kodu'),
                new ReportFilterDefinition('date_from', 'Teslim başlangıç', 'date'),
                new ReportFilterDefinition('date_to', 'Teslim bitiş', 'date'),
                new ReportFilterDefinition('status', 'Durum', 'select', allowedValues: ['draft', 'in_transit', 'customs', 'received', 'closed']),
            ],
            columns: [
                new ReportColumnDefinition('id', 'İthalat ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('product_id', 'Ürün ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('import_number', 'İthalat Dosyası'),
                new ReportColumnDefinition('received_at', 'Teslim Tarihi', 'date'),
                new ReportColumnDefinition('status', 'Durum'),
                new ReportColumnDefinition('supplier_code', 'Tedarikçi Kodu'),
                new ReportColumnDefinition('supplier_title', 'Tedarikçi'),
                new ReportColumnDefinition('product_code', 'Ürün Kodu'),
                new ReportColumnDefinition('product_name', 'Ürün Adı'),
                new ReportColumnDefinition('unit_name', 'Birim'),
                new ReportColumnDefinition('import_quantity', 'Dosyadaki Ürün Miktarı', 'quantity'),
                new ReportColumnDefinition('container_count', 'Konteyner Sayısı', 'integer'),
                new ReportColumnDefinition('package_count', 'Koli Kaydı', 'integer'),
            ],
            defaultSort: [
                new ReportSort('import_number'),
                new ReportSort('product_code'),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')->table('packages as pk')
            ->join('import_files as f', 'f.id', '=', 'pk.import_file_id')
            ->join('products as p', 'p.id', '=', 'pk.product_id')
            ->leftJoin('contacts as supplier', 'supplier.id', '=', 'f.supplier_contact_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->whereNotNull('pk.product_id');

        if (isset($context->filters['import_number'])) {
            $base->whereRaw('UPPER(f.number) = UPPER(?)', [$context->filters['import_number']]);
        }
        if (isset($context->filters['supplier_code'])) {
            $base->whereRaw('UPPER(supplier.code) = UPPER(?)', [$context->filters['supplier_code']]);
        }
        if (isset($context->filters['product_code'])) {
            $base->whereRaw('UPPER(p.code) = UPPER(?)', [$context->filters['product_code']]);
        }
        if (isset($context->filters['date_from'])) {
            $base->whereDate('f.received_at', '>=', $context->filters['date_from']);
        }
        if (isset($context->filters['date_to'])) {
            $base->whereDate('f.received_at', '<=', $context->filters['date_to']);
        }
        if (isset($context->filters['status'])) {
            $base->where('f.status', $context->filters['status']);
        }

        $countQuery = (clone $base)->selectRaw('f.id AS import_id, p.id AS product_id')->groupBy('f.id', 'p.id');
        $totalRows = DB::connection('period')->query()->fromSub($countQuery, 'grouped_import_products')->count();

        $rows = (clone $base)->selectRaw(
            'f.id AS id, p.id AS product_id, f.number AS import_number, f.received_at::text AS received_at, '
            .'f.status AS status, supplier.code AS supplier_code, supplier.title AS supplier_title, '
            .'p.code AS product_code, p.name AS product_name, u.name AS unit_name, '
            .'SUM(pk.quantity)::text AS import_quantity, '
            .'COUNT(DISTINCT pk.container_id)::integer AS container_count, COUNT(pk.id)::integer AS package_count'
        )->groupBy(
            'f.id', 'f.number', 'f.received_at', 'f.status',
            'supplier.code', 'supplier.title',
            'p.id', 'p.code', 'p.name', 'u.name',
        );

        $sortMap = [
            'id' => 'f.id',
            'product_id' => 'p.id',
            'import_number' => 'f.number',
            'received_at' => 'f.received_at',
            'status' => 'f.status',
            'supplier_code' => 'supplier.code',
            'supplier_title' => 'supplier.title',
            'product_code' => 'p.code',
            'product_name' => 'p.name',
            'unit_name' => 'u.name',
            'import_quantity' => 'SUM(pk.quantity)',
            'container_count' => 'COUNT(DISTINCT pk.container_id)',
            'package_count' => 'COUNT(pk.id)',
        ];

        foreach ($context->sort as $sort) {
            $rows->orderByRaw($sortMap[$sort->key].' '.$sort->direction);
        }
        $rows->orderBy('f.id')->orderBy('p.id');

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)
                ->get()->map(fn (object $row): array => (array) $row)->all(),
            totals: [],
            totalRows: $totalRows,
        );
    }
}
