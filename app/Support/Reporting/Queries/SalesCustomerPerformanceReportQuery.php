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
 * Posted sales and customer returns by contact and original invoice currency.
 * Values are billed (not collected) and VAT-excluded, with returns subtracted.
 */
final class SalesCustomerPerformanceReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'sales.customer_performance',
            title: 'Müşteri Bazlı Net Satış Performansı',
            category: 'Satış',
            permission: 'sales_invoices.view',
            filters: [
                new ReportFilterDefinition('date_from', 'Başlangıç', 'date'),
                new ReportFilterDefinition('date_to', 'Bitiş', 'date'),
                new ReportFilterDefinition('contact_id', 'Cari', 'positive_integer'),
                new ReportFilterDefinition('currency', 'Döviz'),
            ],
            columns: [
                new ReportColumnDefinition('contact_id', 'Cari ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('contact_code', 'Cari Kodu'),
                new ReportColumnDefinition('contact_title', 'Müşteri'),
                new ReportColumnDefinition('currency', 'Döviz'),
                new ReportColumnDefinition('invoice_count', 'Satış Faturası', 'integer'),
                new ReportColumnDefinition('return_count', 'Satış İadesi', 'integer'),
                new ReportColumnDefinition('invoice_net', 'Faturalanan (KDV Hariç)', 'decimal'),
                new ReportColumnDefinition('return_net', 'İade (KDV Hariç)', 'decimal'),
                new ReportColumnDefinition('net_sales', 'Net Satış (KDV Hariç)', 'decimal'),
            ],
            defaultSort: [new ReportSort('net_sales', 'desc'), new ReportSort('contact_code')],
            totals: [
                new ReportTotalDefinition('invoice_net', 'Faturalanan (KDV Hariç)'),
                new ReportTotalDefinition('return_net', 'İade (KDV Hariç)'),
                new ReportTotalDefinition('net_sales', 'Net Satış (KDV Hariç)'),
            ],
            drillDowns: [
                new ReportDrillDownDefinition('customer', 'Cari', 'contacts.view', 'contact_id', 'contacts'),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')->table('documents as d')
            ->leftJoin('contacts as c', 'c.id', '=', 'd.contact_id')
            ->whereIn('d.document_type', ['sales_invoice', 'sales_return'])
            ->where('d.status', 'posted')
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('document_relations as r')
                    ->where('r.relation_type', 'reversal_of')
                    ->where(function (Builder $paired): void {
                        $paired->whereColumn('r.source_document_id', 'd.id')
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
        if (isset($filters['contact_id'])) {
            $base->where('d.contact_id', $filters['contact_id']);
        }
        if (isset($filters['currency'])) {
            $base->where('d.currency', strtoupper((string) $filters['currency']));
        }

        $invoiceCount = "COUNT(*) FILTER (WHERE d.document_type = 'sales_invoice')";
        $returnCount = "COUNT(*) FILTER (WHERE d.document_type = 'sales_return')";
        $invoice = "COALESCE(SUM(CASE WHEN d.document_type = 'sales_invoice' THEN d.tax_base ELSE 0 END),0)";
        $returns = "COALESCE(SUM(CASE WHEN d.document_type = 'sales_return' THEN d.tax_base ELSE 0 END),0)";
        $net = "($invoice - $returns)";
        $columns = [
            'contact_id' => 'd.contact_id',
            'contact_code' => 'c.code',
            'contact_title' => 'c.title',
            'currency' => 'd.currency',
            'invoice_count' => $invoiceCount,
            'return_count' => $returnCount,
            'invoice_net' => $invoice,
            'return_net' => $returns,
            'net_sales' => $net,
        ];

        $grouped = (clone $base)->selectRaw('d.contact_id, d.currency')
            ->groupBy('d.contact_id', 'd.currency');
        $count = DB::connection('period')->query()->fromSub($grouped, 'customer_currency')->count();
        $rows = (clone $base)->groupBy('d.contact_id', 'c.code', 'c.title', 'd.currency');

        if (! $context->hasColumn('contact_id')) {
            $rows->selectRaw('d.contact_id AS contact_id');
        }
        foreach ($context->columns as $column) {
            $expr = $columns[$column];
            $number = in_array($column, ['invoice_net', 'return_net', 'net_sales'], true);
            $rows->selectRaw('('.$expr.')'.($number ? '::text' : '').' AS '.$column);
        }
        foreach ($context->sort as $sort) {
            $rows->orderByRaw($columns[$sort->key].' '.$sort->direction);
        }
        $rows->orderBy('d.contact_id')->orderBy('d.currency');

        $totals = [];
        if ($context->totalKeys !== []) {
            $byCurrency = (clone $base)->selectRaw(
                "d.currency AS currency, $invoice::text AS invoice_net, ".
                "$returns::text AS return_net, $net::text AS net_sales"
            )->groupBy('d.currency')->get();
            $totals = CurrencySeparatedTotals::fromGroups(
                $byCurrency, $context->totalKeys, $filters['currency'] ?? null,
            );
        }

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)
                ->get()->map(fn (object $r): array => (array) $r)->all(),
            totals: $totals,
            totalRows: $count,
        );
    }
}
