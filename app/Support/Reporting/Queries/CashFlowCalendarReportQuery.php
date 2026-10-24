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
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * A cash-position calendar, not a promise of collections or a bank statement.
 * Actual: cash plus BOOK-origin bank movements posted on/before as_of.
 * Forecast: remaining net contact ledger exposure, FIFO allocated within
 * each contact/currency. Instruments are NON-ADDITIVE memo columns: counting
 * them again would duplicate receivables/payables without settlement linkage.
 *
 * No currency conversion, no partner capital/profit calculations.
 */
final class CashFlowCalendarReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'finance.cash_flow_calendar',
            title: 'Nakit Akış Takvimi',
            category: 'Finans',
            permission: 'finance_movements.view',
            filters: [
                new ReportFilterDefinition('as_of', 'Hesaplama Tarihi', 'date'),
                new ReportFilterDefinition('date_from', 'Takvim Başlangıç', 'date'),
                new ReportFilterDefinition('date_to', 'Takvim Bitiş', 'date'),
                new ReportFilterDefinition('granularity', 'Gruplama', 'select', allowedValues: ['day', 'week', 'month']),
                new ReportFilterDefinition('currency', 'Döviz'),
            ],
            columns: [
                new ReportColumnDefinition('period_start', 'Takvim Başlangıcı', 'date'),
                new ReportColumnDefinition('period_end', 'Takvim Bitişi', 'date'),
                new ReportColumnDefinition('currency', 'Döviz'),
                new ReportColumnDefinition('opening_balance', 'Açılış Nakit', 'decimal'),
                new ReportColumnDefinition('actual_in', 'Gerçekleşen Giriş', 'decimal'),
                new ReportColumnDefinition('actual_out', 'Gerçekleşen Çıkış', 'decimal'),
                new ReportColumnDefinition('expected_in', 'Beklenen Tahsilat', 'decimal'),
                new ReportColumnDefinition('expected_out', 'Beklenen Ödeme', 'decimal'),
                new ReportColumnDefinition('closing_balance', 'Tahmini Kapanış', 'decimal'),
                new ReportColumnDefinition('security_in_reference', 'Alınan Çek/Senet (Referans)', 'decimal'),
                new ReportColumnDefinition('security_out_reference', 'Verilen Çek/Senet (Referans)', 'decimal'),
                new ReportColumnDefinition('cash_alert', 'Nakit Durumu', sortable: false),
            ],
            defaultSort: [new ReportSort('period_start'), new ReportSort('currency')],
            totals: [
                new ReportTotalDefinition('actual_in', 'Gerçekleşen Giriş'),
                new ReportTotalDefinition('actual_out', 'Gerçekleşen Çıkış'),
                new ReportTotalDefinition('expected_in', 'Beklenen Tahsilat'),
                new ReportTotalDefinition('expected_out', 'Beklenen Ödeme'),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $today = CarbonImmutable::today(config('app.timezone'));
        $asOf = CarbonImmutable::parse($context->filters['as_of'] ?? $today->toDateString());
        $start = CarbonImmutable::parse($context->filters['date_from'] ?? $asOf->subDays(7)->toDateString());
        $end = CarbonImmutable::parse($context->filters['date_to'] ?? $asOf->addDays(90)->toDateString());
        $granularity = $context->filters['granularity'] ?? 'day';
        $selected = isset($context->filters['currency'])
            ? strtoupper((string) $context->filters['currency']) : null;

        if ($end->lessThan($start) || abs((float) $end->diffInDays($start)) > 366
            || $asOf->lessThan($start) || $asOf->greaterThan($end)
            || ! in_array($granularity, ['day', 'week', 'month'], true)
            || ($selected !== null && preg_match('/^[A-Z]{3}$/D', $selected) !== 1)) {
            throw new DomainException('Nakit akış takvimi tarih, aralık veya döviz filtresi geçersiz.');
        }

        $asOfDate = $asOf->toDateString();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $opening = [];
        $actual = [];
        foreach ([
            ['cash_movements', 'cash_accounts', 'cash_account_id', false],
            ['bank_movements', 'bank_accounts', 'bank_account_id', true],
        ] as [$movement, $account, $foreignKey, $bank]) {
            $base = DB::connection('period')->table($movement.' as m')
                ->join($account.' as a', 'a.id', '=', 'm.'.$foreignKey)
                ->where('m.movement_date', '<=', $asOfDate);
            if ($bank) {
                $base->where('m.origin', 'book'); // Bank statement imports are not additional cash.
            }
            if ($selected !== null) {
                $base->where('a.currency', $selected);
            }

            $daily = (clone $base)
                ->where('m.movement_date', '>=', $startDate)
                ->selectRaw("m.movement_date AS date, a.currency,
                    COALESCE(SUM(CASE WHEN m.direction='in' THEN m.amount ELSE 0 END),0)::text AS money_in,
                    COALESCE(SUM(CASE WHEN m.direction='out' THEN m.amount ELSE 0 END),0)::text AS money_out")
                ->groupBy('m.movement_date', 'a.currency')->get();

            foreach ($daily as $row) {
                $date = (string) $row->date;
                $currency = strtoupper((string) $row->currency);
                $this->addDaily($actual, $date, $currency, 'actual_in', (string) $row->money_in);
                $this->addDaily($actual, $date, $currency, 'actual_out', (string) $row->money_out);
            }

            $past = (clone $base)->where('m.movement_date', '<', $startDate)
                ->selectRaw("a.currency,
                    COALESCE(SUM(CASE WHEN m.direction='in' THEN m.amount ELSE -m.amount END),0)::text AS net")
                ->groupBy('a.currency')->get();

            foreach ($past as $row) {
                $currency = strtoupper((string) $row->currency);
                $opening[$currency] = bcadd($opening[$currency] ?? '0.0000', (string) $row->net, 4);
            }
        }

        $expected = [];
        $ledger = DB::connection('period')->table('contact_transactions')
            ->where('transaction_date', '<=', $asOfDate)
            ->when($selected !== null, fn ($query) => $query->where('currency', $selected))
            ->orderBy('contact_id')->orderBy('currency')->orderBy('id')
            ->get(['id', 'contact_id', 'currency', 'direction', 'amount', 'transaction_date', 'due_date', 'reversal_of_id']);

        // Group per contact + currency; offsets never cross either boundary.
        foreach ($ledger->groupBy(fn (object $item): string => $item->contact_id.'|'.$item->currency) as $group) {
            $currency = strtoupper((string) $group->first()->currency);
            $byId = $group->keyBy('id');
            $reversed = [];

            foreach ($group as $line) {
                if ($line->reversal_of_id === null) {
                    continue;
                }
                $original = $byId->get($line->reversal_of_id);
                if ($original && $original->direction !== $line->direction
                    && bccomp((string) $original->amount, (string) $line->amount, 4) === 0) {
                    $reversed[(int) $original->id] = true;
                    $reversed[(int) $line->id] = true;
                }
            }

            $active = $group->reject(fn (object $item): bool => isset($reversed[(int) $item->id]));
            $debit = '0.0000';
            $credit = '0.0000';
            foreach ($active as $line) {
                if ($line->direction === 'debit') {
                    $debit = bcadd($debit, (string) $line->amount, 4);
                } else {
                    $credit = bcadd($credit, (string) $line->amount, 4);
                }
            }

            $net = bcsub($debit, $credit, 4);
            if (bccomp($net, '0', 4) === 0) {
                continue;
            }

            $receive = bccomp($net, '0', 4) > 0;
            $offset = $receive ? $credit : $debit;
            $lines = $active->filter(
                fn (object $item): bool => $item->direction === ($receive ? 'debit' : 'credit')
            )->sort(function (object $left, object $right): int {
                return [
                    (string) ($left->due_date ?? $left->transaction_date),
                    (string) $left->transaction_date, (int) $left->id,
                ] <=> [
                    (string) ($right->due_date ?? $right->transaction_date),
                    (string) $right->transaction_date, (int) $right->id,
                ];
            });

            foreach ($lines as $line) {
                $amount = (string) $line->amount;
                $covered = bccomp($offset, $amount, 4) >= 0 ? $amount : $offset;
                $offset = bcsub($offset, $covered, 4);
                $remaining = bcsub($amount, $covered, 4);

                if (bccomp($remaining, '0', 4) <= 0) {
                    continue;
                }

                $due = (string) ($line->due_date ?? $line->transaction_date);
                // Outstanding overdue items enter the next forecasting day,
                // never yesterday's realized cash balance.
                $forecastDate = $due <= $asOfDate ? $asOf->addDay()->toDateString() : $due;
                if ($forecastDate <= $endDate) {
                    $this->addDaily($expected, $forecastDate, $currency,
                        $receive ? 'expected_in' : 'expected_out', $remaining);
                }
            }
        }

        // Security commitments are shown as reference only. They may already
        // be represented by receivable/payable ledger lines.
        $securities = DB::connection('period')->table('securities')
            ->whereIn('status', ['portfolio', 'banked', 'issued'])
            ->where('due_date', '<=', $endDate)
            ->when($selected !== null, fn ($query) => $query->where('currency', $selected))
            ->get(['direction', 'status', 'due_date', 'currency', 'amount']);

        $reference = [];
        foreach ($securities as $item) {
            $incoming = $item->direction === 'incoming'
                && in_array($item->status, ['portfolio', 'banked'], true);
            $outgoing = $item->direction === 'outgoing' && $item->status === 'issued';
            if (! $incoming && ! $outgoing) {
                continue;
            }
            $due = (string) $item->due_date;
            $date = $due <= $asOfDate ? $asOf->addDay()->toDateString() : $due;
            $this->addDaily($reference, $date, strtoupper((string) $item->currency),
                $incoming ? 'security_in_reference' : 'security_out_reference', (string) $item->amount);
        }

        $currencies = array_keys($opening);
        foreach ([$actual, $expected, $reference] as $dates) {
            foreach ($dates as $currencyValues) {
                foreach (array_keys($currencyValues) as $currency) {
                    $currencies[] = $currency;
                }
            }
        }
        $currencies = array_values(array_unique($currencies));
        if ($selected !== null && ! in_array($selected, $currencies, true)) {
            $currencies[] = $selected;
        }
        sort($currencies);

        $rows = [];
        $totalsByCurrency = [];
        foreach ($currencies as $currency) {
            $running = $opening[$currency] ?? '0.0000';
            $buckets = [];

            for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
                $dateKey = $date->toDateString();
                $bucketKey = match ($granularity) {
                    'week' => $date->startOfWeek()->toDateString(),
                    'month' => $date->startOfMonth()->toDateString(),
                    default => $dateKey,
                };

                if (! isset($buckets[$bucketKey])) {
                    $buckets[$bucketKey] = [
                        'period_start' => $dateKey, 'period_end' => $dateKey,
                        'currency' => $currency, 'opening_balance' => $running,
                        'actual_in' => '0.0000', 'actual_out' => '0.0000',
                        'expected_in' => '0.0000', 'expected_out' => '0.0000',
                        'closing_balance' => $running,
                        'security_in_reference' => '0.0000', 'security_out_reference' => '0.0000',
                        'cash_alert' => '',
                    ];
                }

                $bucket = &$buckets[$bucketKey];
                $bucket['period_end'] = $dateKey;
                foreach (['actual_in','actual_out','expected_in','expected_out',
                    'security_in_reference','security_out_reference'] as $field) {
                    $source = match ($field) {
                        'actual_in', 'actual_out' => $actual,
                        'expected_in', 'expected_out' => $expected,
                        default => $reference,
                    };
                    $value = $source[$dateKey][$currency][$field] ?? '0.0000';
                    $bucket[$field] = bcadd($bucket[$field], $value, 4);
                }

                $in = bcadd($actual[$dateKey][$currency]['actual_in'] ?? '0.0000',
                    $expected[$dateKey][$currency]['expected_in'] ?? '0.0000', 4);
                $out = bcadd($actual[$dateKey][$currency]['actual_out'] ?? '0.0000',
                    $expected[$dateKey][$currency]['expected_out'] ?? '0.0000', 4);
                $running = bcsub(bcadd($running, $in, 4), $out, 4);
                $bucket['closing_balance'] = $running;
                $bucket['cash_alert'] = bccomp($running, '0', 4) < 0 ? 'Nakit açığı' : 'Normal';
                unset($bucket);
            }
            foreach ($buckets as $bucket) {
                $rows[] = $bucket;
                foreach (['actual_in','actual_out','expected_in','expected_out'] as $field) {
                    $totalsByCurrency[$currency][$field] = bcadd(
                        $totalsByCurrency[$currency][$field] ?? '0.0000', $bucket[$field], 4);
                }
            }
        }

        foreach (array_reverse($context->sort) as $sort) {
            usort($rows, static function (array $a, array $b) use ($sort): int {
                $key = $sort->key;
                $numeric = in_array($key, ['opening_balance','actual_in','actual_out',
                    'expected_in','expected_out','closing_balance',
                    'security_in_reference','security_out_reference'], true);
                $comparison = $numeric
                    ? bccomp($a[$key], $b[$key], 4)
                    : ($a[$key] <=> $b[$key]);
                return $sort->direction === 'desc' ? -$comparison : $comparison;
            });
        }

        $totals = [];
        if ($context->totalKeys !== []) {
            $currencyGroups = [];
            foreach ($totalsByCurrency as $currency => $amounts) {
                $currencyGroups[] = (object) ['currency' => $currency, ...$amounts];
            }
            $totals = CurrencySeparatedTotals::fromGroups(
                $currencyGroups, $context->totalKeys, $selected,
            );
        }

        return new ReportQueryResult(
            rows: array_map(
                static fn (array $row): array => array_intersect_key($row, array_flip($context->columns)),
                array_slice($rows, $context->offset, $context->limit),
            ),
            totals: $totals,
            totalRows: count($rows),
        );
    }

    /** @param array<string,array<string,array<string,string>>> $daily */
    private function addDaily(array &$daily, string $date, string $currency, string $field, string $amount): void
    {
        $daily[$date][$currency][$field] = bcadd(
            $daily[$date][$currency][$field] ?? '0.0000', $amount, 4);
    }
}
