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
 * Posted supplier invoices grouped by supplier and invoice currency.
 * Purchase returns are not silently subtracted: they have a separate report.
 */
final class SupplierPurchasePerformanceReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'purchases.supplier_performance',
            title: 'Tedarikçi Bazlı Alış Analizi',
            category: 'Alış',
            permission: 'supplier_invoices.view',
            filters: [
                new ReportFilterDefinition('date_from', 'Başlangıç', 'date'),
                new ReportFilterDefinition('date_to', 'Bitiş', 'date'),
                new ReportFilterDefinition('contact_id', 'Tedarikçi', 'positive_integer'),
                new ReportFilterDefinition('currency', 'Döviz'),
            ],
            columns: [
                new ReportColumnDefinition('contact_id', 'Cari ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('supplier_code', 'Tedarikçi Kodu'),
                new ReportColumnDefinition('supplier_title', 'Tedarikçi'),
                new ReportColumnDefinition('currency', 'Döviz'),
                new ReportColumnDefinition('invoice_count', 'Fatura Sayısı', 'integer'),
                new ReportColumnDefinition('subtotal', 'İskontosuz Satır Tutarı', 'decimal'),
                new ReportColumnDefinition('net_amount', 'KDV Hariç Alış', 'decimal'),
                new ReportColumnDefinition('vat_amount', 'KDV', 'decimal'),
                new ReportColumnDefinition('grand_total', 'KDV Dahil Alış', 'decimal'),
            ],
            defaultSort: [new ReportSort('net_amount', 'desc'), new ReportSort('supplier_code')],
            totals: [
                new ReportTotalDefinition('net_amount', 'KDV Hariç Alış'),
                new ReportTotalDefinition('vat_amount', 'KDV'),
                new ReportTotalDefinition('grand_total', 'KDV Dahil Alış'),
            ],
            drillDowns: [
                new ReportDrillDownDefinition('supplier', 'Cari', 'contacts.view', 'contact_id', 'contacts'),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')->table('documents as d')
            ->leftJoin('contacts as c', 'c.id', '=', 'd.contact_id')
            ->where('d.document_type', 'supplier_invoice')
            ->where('d.status', 'posted')
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('document_relations as r')
                    ->where('r.relation_type', 'reversal_of')
                    ->where(function (Builder $paired): void {
                        $paired->whereColumn('r.source_document_id', 'd.id')
                            ->orWhereColumn('r.target_document_id', 'd.id');
                    });
            });

        $f = $context->filters;
        if (isset($f['date_from'])) {
            $base->whereDate('d.document_date', '>=', $f['date_from']);
        }
        if (isset($f['date_to'])) {
            $base->whereDate('d.document_date', '<=', $f['date_to']);
        }
        if (isset($f['contact_id'])) {
            $base->where('d.contact_id', $f['contact_id']);
        }
        if (isset($f['currency'])) {
            $base->where('d.currency', strtoupper((string) $f['currency']));
        }

        $fields = [
            'contact_id' => 'd.contact_id',
            'supplier_code' => 'c.code',
            'supplier_title' => 'c.title',
            'currency' => 'd.currency',
            'invoice_count' => 'COUNT(d.id)',
            'subtotal' => 'COALESCE(SUM(d.subtotal),0)',
            'net_amount' => 'COALESCE(SUM(d.tax_base),0)',
            'vat_amount' => 'COALESCE(SUM(d.vat_amount),0)',
            'grand_total' => 'COALESCE(SUM(d.grand_total),0)',
        ];
        $groups = (clone $base)->selectRaw('d.contact_id,d.currency')
            ->groupBy('d.contact_id', 'd.currency');
        $totalRows = DB::connection('period')->query()
            ->fromSub($groups, 'supplier_currency')->count();

        $rows = (clone $base)->groupBy('d.contact_id','c.code','c.title','d.currency');
        if (! $context->hasColumn('contact_id')) {
            $rows->selectRaw('d.contact_id AS contact_id');
        }
        foreach ($context->columns as $key) {
            $numeric = in_array($key, ['subtotal','net_amount','vat_amount','grand_total'], true);
            $rows->selectRaw('('.$fields[$key].')'.($numeric ? '::text' : '').' AS '.$key);
        }
        foreach ($context->sort as $sort) {
            $rows->orderByRaw($fields[$sort->key].' '.$sort->direction);
        }
        $rows->orderBy('d.contact_id')->orderBy('d.currency');

        $totals = [];
        if ($context->totalKeys !== []) {
            $byCurrency = (clone $base)
                ->selectRaw(
                    'd.currency AS currency, COALESCE(SUM(d.tax_base),0)::text AS net_amount, '.
                    'COALESCE(SUM(d.vat_amount),0)::text AS vat_amount, '.
                    'COALESCE(SUM(d.grand_total),0)::text AS grand_total'
                )->groupBy('d.currency')->get();
            $totals = CurrencySeparatedTotals::fromGroups($byCurrency, $context->totalKeys, $f['currency'] ?? null);
        }

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)
                ->get()->map(fn (object $row): array => (array) $row)->all(),
            totals: $totals,
            totalRows: $totalRows,
        );
    }
}
