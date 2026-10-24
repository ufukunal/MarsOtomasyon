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
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Calendar-based maturity classification. Status is always displayed to
 * avoid implying paid/cancelled securities are still collectible or due.
 */
final class SecuritiesMaturityReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'securities.maturities',
            title: 'Çek / Senet Vade Takvimi',
            category: 'Çek / Senet',
            permission: 'securities.view',
            filters: [
                new ReportFilterDefinition('as_of', 'Hesaplama Tarihi', 'date'),
                new ReportFilterDefinition('date_from', 'Vade Başlangıç', 'date'),
                new ReportFilterDefinition('date_to', 'Vade Bitiş', 'date'),
                new ReportFilterDefinition('contact_id', 'Cari', 'positive_integer'),
                new ReportFilterDefinition('direction', 'Yön', 'select', allowedValues: ['incoming','outgoing']),
                new ReportFilterDefinition('kind', 'Tür', 'select', allowedValues: ['check','note']),
                new ReportFilterDefinition('status', 'Durum'),
                new ReportFilterDefinition('currency', 'Döviz'),
            ],
            columns: [
                new ReportColumnDefinition('id', 'Belge ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('instrument_no', 'Çek / Senet No'),
                new ReportColumnDefinition('contact_code', 'Cari Kod'),
                new ReportColumnDefinition('contact_title', 'Cari'),
                new ReportColumnDefinition('kind', 'Tür'),
                new ReportColumnDefinition('direction', 'Yön'),
                new ReportColumnDefinition('issue_date', 'Düzenleme Tarihi', 'date'),
                new ReportColumnDefinition('due_date', 'Vade Tarihi', 'date'),
                new ReportColumnDefinition('days_to_due', 'Vadeye Gün', 'integer'),
                new ReportColumnDefinition('due_group', 'Takvim Vade Grubu', sortable: false),
                new ReportColumnDefinition('status', 'İşlem Durumu'),
                new ReportColumnDefinition('currency', 'Döviz'),
                new ReportColumnDefinition('amount', 'Tutar', 'decimal'),
            ],
            defaultSort: [new ReportSort('due_date'), new ReportSort('id')],
            totals: [new ReportTotalDefinition('amount', 'Toplam Tutar')],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $asOf = $context->filters['as_of'] ?? CarbonImmutable::today(config('app.timezone'))->toDateString();

        $base = DB::connection('period')->table('securities as s')
            ->leftJoin('contacts as c', 'c.id', '=', 's.contact_id');
        $f = $context->filters;

        if (isset($f['date_from'])) {
            $base->whereDate('s.due_date', '>=', $f['date_from']);
        }
        if (isset($f['date_to'])) {
            $base->whereDate('s.due_date', '<=', $f['date_to']);
        }
        foreach (['contact_id', 'direction', 'kind', 'status', 'currency'] as $key) {
            if (isset($f[$key])) {
                $base->where('s.'.$key, $key === 'currency'
                    ? strtoupper((string) $f[$key]) : $f[$key]);
            }
        }

        $fields = [
            'id' => 's.id',
            'instrument_no' => 's.instrument_no',
            'contact_code' => 'c.code',
            'contact_title' => 'c.title',
            'kind' => 's.kind',
            'direction' => 's.direction',
            'issue_date' => 's.issue_date',
            'due_date' => 's.due_date',
            'days_to_due' => '(s.due_date - ?::date)',
            'status' => 's.status',
            'currency' => 's.currency',
            'amount' => 's.amount',
        ];

        $rows = clone $base;
        if (! $context->hasColumn('id')) {
            $rows->selectRaw('s.id AS id');
        }
        foreach ($context->columns as $key) {
            if ($key === 'due_group') {
                $rows->selectRaw(
                    "CASE WHEN s.due_date < ?::date THEN 'Geçmiş tarih'
                        WHEN s.due_date <= (?::date + 7) THEN '0-7 gün'
                        WHEN s.due_date <= (?::date + 30) THEN '8-30 gün'
                        WHEN s.due_date <= (?::date + 90) THEN '31-90 gün'
                        ELSE '90 gün üzeri' END AS due_group",
                    [$asOf, $asOf, $asOf, $asOf],
                );
                continue;
            }
            $dateOrMoney = in_array($key, ['issue_date','due_date','amount'], true);
            $rows->selectRaw(
                $fields[$key].($dateOrMoney ? '::text' : '').' AS '.$key,
                $key === 'days_to_due' ? [$asOf] : [],
            );
        }

        foreach ($context->sort as $sort) {
            if ($sort->key === 'days_to_due') {
                $rows->orderByRaw($fields[$sort->key].' '.$sort->direction, [$asOf]);
            } else {
                $rows->orderBy($fields[$sort->key], $sort->direction);
            }
        }
        $rows->orderBy('s.id');
        $totals = [];
        if ($context->hasTotal('amount')) {
            $groups = (clone $base)->selectRaw(
                's.currency AS currency, COALESCE(SUM(s.amount),0)::text AS amount'
            )->groupBy('s.currency')->get();
            $totals = CurrencySeparatedTotals::fromGroups(
                $groups, ['amount'], $f['currency'] ?? null,
            );
        }

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)
                ->get()->map(fn (object $row): array => (array) $row)->all(),
            totals: $totals,
            totalRows: (clone $base)->count('s.id'),
        );
    }
}
