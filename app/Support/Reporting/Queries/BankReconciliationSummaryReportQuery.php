<?php

namespace App\Support\Reporting\Queries;

use App\Contracts\Reporting\ReportQuery;
use App\Support\Reporting\CurrencySeparatedTotals;
use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportDefinition;
use App\Support\Reporting\ReportExecutionContext;
use App\Support\Reporting\ReportFilterDefinition;
use App\Support\Reporting\ReportQueryResult;
use App\Support\Reporting\ReportSort;
use App\Support\Reporting\ReportTotalDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Bank statement row matching completeness, per account and currency.
 * Unmatched amounts are not necessarily real accounting discrepancies.
 */
final class BankReconciliationSummaryReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'finance.bank_reconciliation',
            title: 'Banka Ekstresi Mutabakat Özeti',
            category: 'Finans',
            permission: 'bank_statements.view',
            filters: [
                new ReportFilterDefinition('date_from', 'Ekstre Başlangıç', 'date'),
                new ReportFilterDefinition('date_to', 'Ekstre Bitiş', 'date'),
                new ReportFilterDefinition('bank_account_id', 'Banka Hesabı', 'positive_integer'),
                new ReportFilterDefinition('only_unmatched', 'Yalnız Eşleşmemiş Hesaplar', 'boolean'),
            ],
            columns: [
                new ReportColumnDefinition('bank_account_id', 'Banka Hesap ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('account_code', 'Hesap Kodu'),
                new ReportColumnDefinition('account_name', 'Banka Hesabı'),
                new ReportColumnDefinition('currency', 'Döviz'),
                new ReportColumnDefinition('statement_count', 'Ekstre Satırı', 'integer'),
                new ReportColumnDefinition('matched_count', 'Eşleşen', 'integer'),
                new ReportColumnDefinition('unmatched_count', 'Eşleşmeyen', 'integer'),
                new ReportColumnDefinition('matching_rate', 'Eşleşme Oranı (%)', 'decimal'),
                new ReportColumnDefinition('unmatched_in', 'Eşleşmemiş Giriş', 'decimal'),
                new ReportColumnDefinition('unmatched_out', 'Eşleşmemiş Çıkış', 'decimal'),
            ],
            defaultSort: [new ReportSort('unmatched_count', 'desc'), new ReportSort('account_code')],
            totals: [
                new ReportTotalDefinition('unmatched_in', 'Eşleşmemiş Giriş'),
                new ReportTotalDefinition('unmatched_out', 'Eşleşmemiş Çıkış'),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')->table('bank_movements as bm')
            ->join('bank_accounts as a', 'a.id', '=', 'bm.bank_account_id')
            ->where('bm.origin', 'statement');

        $filters = $context->filters;
        if (isset($filters['date_from'])) {
            $base->whereDate('bm.movement_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $base->whereDate('bm.movement_date', '<=', $filters['date_to']);
        }
        if (isset($filters['bank_account_id'])) {
            $base->where('bm.bank_account_id', $filters['bank_account_id']);
        }

        $groupFields = ['a.id', 'a.code', 'a.account_name', 'a.currency'];
        $allCount = 'COUNT(bm.id)';
        $matched = 'COUNT(bm.id) FILTER (WHERE bm.reconciled_movement_id IS NOT NULL)';
        $unmatched = 'COUNT(bm.id) FILTER (WHERE bm.reconciled_movement_id IS NULL)';
        $rate = "(CASE WHEN $allCount > 0 THEN ROUND($matched * 100.0 / $allCount, 2) ELSE NULL END)";
        $missingIn = "COALESCE(SUM(CASE WHEN bm.reconciled_movement_id IS NULL AND bm.direction = 'in' THEN bm.amount ELSE 0 END),0)";
        $missingOut = "COALESCE(SUM(CASE WHEN bm.reconciled_movement_id IS NULL AND bm.direction = 'out' THEN bm.amount ELSE 0 END),0)";

        $groups = (clone $base)->groupBy(...$groupFields);
        if (($filters['only_unmatched'] ?? false) === true) {
            $groups->havingRaw($unmatched.' > 0');
        }

        $fields = [
            'bank_account_id' => 'a.id',
            'account_code' => 'a.code',
            'account_name' => 'a.account_name',
            'currency' => 'a.currency',
            'statement_count' => $allCount,
            'matched_count' => $matched,
            'unmatched_count' => $unmatched,
            'matching_rate' => $rate,
            'unmatched_in' => $missingIn,
            'unmatched_out' => $missingOut,
        ];
        $numeric = ['matching_rate', 'unmatched_in', 'unmatched_out'];

        $countGroups = (clone $groups)->selectRaw('a.id AS account_id');
        $totalRows = DB::connection('period')->query()->fromSub($countGroups, 'grouped_statements')->count();
        $rows = clone $groups;

        if (! $context->hasColumn('bank_account_id')) {
            $rows->selectRaw('a.id AS bank_account_id');
        }
        foreach ($context->columns as $column) {
            $rows->selectRaw('('.$fields[$column].')'
                .(in_array($column, $numeric, true) ? '::text' : '').' AS '.$column);
        }
        foreach ($context->sort as $sort) {
            $rows->orderByRaw($fields[$sort->key].' '.$sort->direction);
        }
        $rows->orderBy('a.id');

        $totals = [];
        if ($context->totalKeys !== []) {
            $accountTotals = (clone $groups)->selectRaw(
                "a.currency AS currency, $missingIn AS unmatched_in, $missingOut AS unmatched_out"
            );

            $byCurrency = DB::connection('period')->query()
                ->fromSub($accountTotals, 'account_totals')
                ->selectRaw(
                    'currency, SUM(unmatched_in)::text AS unmatched_in, SUM(unmatched_out)::text AS unmatched_out'
                )->groupBy('currency')->get();

            $totals = CurrencySeparatedTotals::fromGroups($byCurrency, $context->totalKeys);
        }

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)
                ->get()->map(fn (object $r): array => (array) $r)->all(),
            totals: $totals,
            totalRows: $totalRows,
        );
    }
}
