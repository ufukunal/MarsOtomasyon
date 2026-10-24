<?php

namespace App\Support\Reporting\Queries;

use App\Contracts\Reporting\ReportQuery;
use App\Support\Reporting\CurrencySeparatedTotals;
use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportDefinition;
use App\Support\Reporting\ReportDrillDownDefinition;
use App\Support\Reporting\ReportExecutionContext;
use App\Support\Reporting\ReportFilterDefinition;
use App\Support\Reporting\ReportQueryResult;
use App\Support\Reporting\ReportSort;
use App\Support\Reporting\ReportTotalDefinition;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Net sales by product and invoice currency; sales returns have a negative sign.
 * This reports posted documents only and does not imply import-lot attribution.
 */
final class SalesProductPerformanceReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'sales.product_performance',
            title: 'Ürün Bazlı Net Satış Performansı',
            category: 'Satış',
            permission: 'sales_invoices.view',
            filters: [
                new ReportFilterDefinition('date_from', 'Başlangıç', 'date'),
                new ReportFilterDefinition('date_to', 'Bitiş', 'date'),
                new ReportFilterDefinition('product_id', 'Ürün', 'positive_integer'),
                new ReportFilterDefinition('contact_id', 'Cari', 'positive_integer'),
                new ReportFilterDefinition('currency', 'Döviz'),
            ],
            columns: [
                new ReportColumnDefinition('product_id', 'Ürün ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('product_code', 'Ürün Kodu'),
                new ReportColumnDefinition('product_name', 'Ürün'),
                new ReportColumnDefinition('unit_name', 'Temel Birim'),
                new ReportColumnDefinition('currency', 'Döviz'),
                new ReportColumnDefinition('sold_quantity', 'Satış Miktarı', 'quantity'),
                new ReportColumnDefinition('returned_quantity', 'İade Miktarı', 'quantity'),
                new ReportColumnDefinition('net_quantity', 'Net Satış Miktarı', 'quantity'),
                new ReportColumnDefinition('net_amount', 'Net Satış (KDV Hariç)', 'decimal'),
            ],
            defaultSort: [new ReportSort('net_amount', 'desc'), new ReportSort('product_code')],
            totals: [
                new ReportTotalDefinition('net_amount', 'Net Satış (KDV Hariç)'),
            ],
            drillDowns: [
                new ReportDrillDownDefinition('product', 'Ürün', 'products.view', 'product_id', 'products'),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')->table('document_lines as l')
            ->join('documents as d', 'd.id', '=', 'l.document_id')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->where('l.line_kind', 'stock')
            ->where('d.status', 'posted')
            ->whereIn('d.document_type', ['sales_invoice', 'sales_return'])
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('document_relations as r')
                    ->where('r.relation_type', 'reversal_of')
                    ->where(function (Builder $pair): void {
                        $pair->whereColumn('r.source_document_id', 'd.id')
                            ->orWhereColumn('r.target_document_id', 'd.id');
                    });
            });

        $filters = $context->filters;
        if (isset($filters['date_from'])) {
            $base->whereDate('d.document_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $base->whereDate('d.document_date', '<=', $filters['date_to']);
        }
        foreach (['product_id', 'contact_id', 'currency'] as $filter) {
            if (isset($filters[$filter])) {
                $base->where($filter === 'product_id' ? 'l.product_id' : 'd.'.$filter,
                    $filter === 'currency' ? strtoupper((string) $filters[$filter]) : $filters[$filter]);
            }
        }

        $netBeforeSign = '(CASE WHEN d.subtotal > 0 THEN ROUND(l.line_total * d.tax_base / d.subtotal, 4) ELSE 0 END)';
        $sign = "(CASE WHEN d.document_type = 'sales_return' THEN -1 ELSE 1 END)";
        $sold = "SUM(CASE WHEN d.document_type = 'sales_invoice' THEN l.base_quantity ELSE 0 END)";
        $returned = "SUM(CASE WHEN d.document_type = 'sales_return' THEN l.base_quantity ELSE 0 END)";
        $netQuantity = "SUM($sign * l.base_quantity)";
        $netAmount = "SUM($sign * $netBeforeSign)";

        $groups = (clone $base)->selectRaw('l.product_id AS product_id, d.currency AS currency')
            ->groupBy('l.product_id', 'd.currency');
        $totalRows = DB::connection('period')->query()->fromSub($groups, 'product_currency_groups')->count();

        $fields = [
            'product_id' => 'p.id',
            'product_code' => 'p.code',
            'product_name' => 'p.name',
            'unit_name' => 'u.name',
            'currency' => 'd.currency',
            'sold_quantity' => $sold,
            'returned_quantity' => $returned,
            'net_quantity' => $netQuantity,
            'net_amount' => $netAmount,
        ];

        $rows = clone $base;
        $rows->groupBy('p.id', 'p.code', 'p.name', 'u.name', 'd.currency');
        if (! $context->hasColumn('product_id')) {
            $rows->selectRaw('p.id AS product_id');
        }
        foreach ($context->columns as $column) {
            $expr = $fields[$column];
            $rows->selectRaw('('.$expr.')'.(in_array($column,
                ['sold_quantity', 'returned_quantity', 'net_quantity', 'net_amount'], true) ? '::text' : '')
                .' AS '.$column);
        }
        foreach ($context->sort as $sort) {
            $rows->orderByRaw($fields[$sort->key].' '.$sort->direction);
        }
        $rows->orderBy('p.id')->orderBy('d.currency');

        $totals = [];
        if ($context->hasTotal('net_amount')) {
            $byCurrency = (clone $base)
                ->selectRaw('d.currency AS currency, COALESCE('.$netAmount.', 0)::text AS net_amount')
                ->groupBy('d.currency')->get();
            $totals = CurrencySeparatedTotals::fromGroups(
                $byCurrency, ['net_amount'], $filters['currency'] ?? null,
            );
        }

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)
                ->get()->map(fn (object $row): array => (array) $row)->all(),
            totals: $totals,
            totalRows: $totalRows,
        );
    }
}
