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
use Illuminate\Support\Facades\DB;

/**
 * Period-ledger balances by contact AND currency, as of the selected date.
 * Reversals are included as their real offsetting ledger entries.
 */
final class ContactBalanceReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'contacts.balances',
            title: 'Cari Borç / Alacak Bakiyeleri',
            category: 'Cari',
            permission: 'contacts.view',
            filters: [
                new ReportFilterDefinition('date_to', 'Bakiye Tarihi', 'date'),
                new ReportFilterDefinition('contact_id', 'Cari', 'positive_integer'),
                new ReportFilterDefinition('currency', 'Döviz'),
            ],
            columns: [
                new ReportColumnDefinition('contact_id', 'Cari ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('contact_code', 'Cari Kod'),
                new ReportColumnDefinition('contact_title', 'Cari Ünvan'),
                new ReportColumnDefinition('currency', 'Döviz'),
                new ReportColumnDefinition('debit_total', 'Borç Hareketi', 'decimal'),
                new ReportColumnDefinition('credit_total', 'Alacak Hareketi', 'decimal'),
                new ReportColumnDefinition('balance', 'Net Bakiye (Borç − Alacak)', 'decimal'),
                new ReportColumnDefinition('receivable', 'Cari Borçlu', 'decimal'),
                new ReportColumnDefinition('payable', 'Cari Alacaklı', 'decimal'),
            ],
            defaultSort: [new ReportSort('contact_code'), new ReportSort('currency')],
            totals: [
                new ReportTotalDefinition('debit_total', 'Borç Hareketi'),
                new ReportTotalDefinition('credit_total', 'Alacak Hareketi'),
                new ReportTotalDefinition('balance', 'Net Bakiye'),
                new ReportTotalDefinition('receivable', 'Cari Borçlu'),
                new ReportTotalDefinition('payable', 'Cari Alacaklı'),
            ],
            drillDowns: [
                new ReportDrillDownDefinition('contact', 'Cari', 'contacts.view', 'contact_id', 'contacts'),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')->table('contact_transactions as ct')
            ->join('contacts as c', 'c.id', '=', 'ct.contact_id');
        $f = $context->filters;
        if (isset($f['date_to'])) {
            $base->whereDate('ct.transaction_date', '<=', $f['date_to']);
        }
        if (isset($f['contact_id'])) {
            $base->where('ct.contact_id', $f['contact_id']);
        }
        if (isset($f['currency'])) {
            $base->where('ct.currency', strtoupper((string) $f['currency']));
        }

        $debit = "SUM(CASE WHEN ct.direction = 'debit' THEN ct.amount ELSE 0 END)";
        $credit = "SUM(CASE WHEN ct.direction = 'credit' THEN ct.amount ELSE 0 END)";
        $net = "($debit - $credit)";
        $receivable = "GREATEST($net, 0)";
        $payable = "GREATEST(-$net, 0)";

        $groups = (clone $base)->selectRaw('ct.contact_id, ct.currency')
            ->groupBy('ct.contact_id', 'ct.currency');
        $totalRows = DB::connection('period')->query()->fromSub($groups, 'contact_currency')->count();

        $fields = [
            'contact_id' => 'c.id',
            'contact_code' => 'c.code',
            'contact_title' => 'c.title',
            'currency' => 'ct.currency',
            'debit_total' => $debit,
            'credit_total' => $credit,
            'balance' => $net,
            'receivable' => $receivable,
            'payable' => $payable,
        ];

        $rows = (clone $base)->groupBy('c.id', 'c.code', 'c.title', 'ct.currency');
        if (! $context->hasColumn('contact_id')) {
            $rows->selectRaw('c.id AS contact_id');
        }
        foreach ($context->columns as $column) {
            $rows->selectRaw('('.$fields[$column].')'
                .(in_array($column, ['debit_total','credit_total','balance','receivable','payable'], true)
                    ? '::text' : '').' AS '.$column);
        }
        foreach ($context->sort as $sort) {
            $rows->orderByRaw($fields[$sort->key].' '.$sort->direction);
        }
        $rows->orderBy('c.id')->orderBy('ct.currency');

        $totals = [];
        if ($context->totalKeys !== []) {
            $byContact = (clone $base)->selectRaw(
                "ct.contact_id, ct.currency,
                $debit AS debit_total, $credit AS credit_total,
                $net AS balance, $receivable AS receivable, $payable AS payable"
            )->groupBy('ct.contact_id', 'ct.currency');

            $byCurrency = DB::connection('period')->query()->fromSub($byContact, 'x')
                ->selectRaw('x.currency AS currency, SUM(x.debit_total)::text AS debit_total,
                    SUM(x.credit_total)::text AS credit_total, SUM(x.balance)::text AS balance,
                    SUM(x.receivable)::text AS receivable, SUM(x.payable)::text AS payable')
                ->groupBy('x.currency')->get();

            $totals = CurrencySeparatedTotals::fromGroups(
                $byCurrency, $context->totalKeys, $f['currency'] ?? null,
            );
        }

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)
                ->get()->map(fn (object $r): array => (array) $r)->all(),
            totals: $totals,
            totalRows: $totalRows,
        );
    }
}
