<?php

use App\Support\Reporting\Queries\CashFlowCalendarReportQuery;
use App\Support\Reporting\ReportExecutionContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

function marsCashFlowReportContext(array $filters, int $offset = 0, int $limit = 100): ReportExecutionContext
{
    $definition = (new CashFlowCalendarReportQuery)->definition();

    return new ReportExecutionContext(
        filters: $filters,
        columns: $definition->defaultColumnKeys(false),
        sort: $definition->defaultSort,
        totalKeys: array_map(fn ($total): string => $total->key, $definition->totals),
        limit: $limit, offset: $offset, canViewCost: false,
    );
}

it('creates a cash flow calendar from book money and net customer ledger without double counting statement and securities', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $suffix = Str::random(8);
        $now = now();

        $cash = $db->table('cash_accounts')->insertGetId([
            'code' => 'C'.$suffix, 'name' => 'Cash '.$suffix,
            'currency' => 'TRY', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $bank = $db->table('bank_accounts')->insertGetId([
            'code' => 'B'.$suffix, 'bank_name' => 'Example Bank',
            'account_name' => 'Business TRY', 'currency' => 'TRY',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $db->table('cash_movements')->insert([
            'cash_account_id' => $cash, 'movement_date' => '2026-10-20',
            'direction' => 'in', 'amount' => 1000, 'movement_type' => 'manual',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach ([
            ['2026-10-22', 'out', 100, 'book'],
            ['2026-10-24', 'in', 50, 'book'],
            ['2026-10-24', 'in', 900, 'statement'],
            ['2026-10-25', 'in', 500, 'book'], // Future-posted movements aren't future cash receipts here.
        ] as [$date, $direction, $amount, $origin]) {
            $db->table('bank_movements')->insert([
                'bank_account_id' => $bank, 'movement_date' => $date,
                'direction' => $direction, 'amount' => $amount, 'origin' => $origin,
                'movement_type' => 'manual',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $debt = function (string $date, string $direction, int $amount, string $due, string $currency, int $contactId, ?int $reversal = null)
            use ($db, $now): int {
            return (int) $db->table('contact_transactions')->insertGetId([
                'contact_id' => $contactId, 'transaction_type' => 'manual',
                'transaction_date' => $date, 'due_date' => $due,
                'direction' => $direction, 'amount' => $amount,
                'currency' => $currency, 'reversal_of_id' => $reversal,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        };

        $contacts = [];
        for ($i = 0; $i < 4; $i++) {
            $contacts[] = $db->table('contacts')->insertGetId([
                'title' => 'Cash-flow customer '.$suffix.' '.$i,
                'type' => 'legal', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Customer 1: 150 receivable, 50 collected => only 100 expected.
        $debt('2026-10-19', 'debit', 150, '2026-10-27', 'TRY', $contacts[0]);
        $debt('2026-10-23', 'credit', 50, '2026-10-23', 'TRY', $contacts[0]);

        // Customer 2: 80 payable, 20 paid => 60 expected out.
        $debt('2026-10-19', 'credit', 80, '2026-10-28', 'TRY', $contacts[1]);
        $debt('2026-10-22', 'debit', 20, '2026-10-22', 'TRY', $contacts[1]);

        // Overdue receivable moves to first forecast day, not to historical cash.
        $debt('2026-10-18', 'debit', 30, '2026-10-20', 'TRY', $contacts[2]);

        // Reversal pair must not move the actual forecast.
        $original = $debt('2026-10-20', 'debit', 100, '2026-10-30', 'TRY', $contacts[3]);
        $debt('2026-10-23', 'credit', 100, '2026-10-23', 'TRY', $contacts[3], $original);

        $security = [
            'fingerprint' => hash('sha256', 'cash-flow'.$suffix),
            'instrument_no' => 'CF-'.$suffix,
            'contact_id' => $contacts[0],
            'direction' => 'incoming', 'kind' => 'check',
            'due_date' => '2026-10-27', 'currency' => 'TRY',
            'amount' => 300, 'status' => 'portfolio',
            'created_at' => $now, 'updated_at' => $now,
        ];
        $db->table('securities')->insert($security);

        $query = new CashFlowCalendarReportQuery;
        $filters = [
            'as_of' => '2026-10-24',
            'date_from' => '2026-10-24',
            'date_to' => '2026-10-30',
            'currency' => 'TRY',
        ];
        $result = $query->execute(marsCashFlowReportContext($filters));
        $rows = collect($result->rows)->keyBy('period_start');

        expect($result->totalRows)->toBe(7)
            ->and((float) $rows['2026-10-24']['opening_balance'])->toBe(900.0)
            ->and((float) $rows['2026-10-24']['actual_in'])->toBe(50.0)
            ->and((float) $rows['2026-10-24']['closing_balance'])->toBe(950.0)
            ->and((float) $rows['2026-10-25']['expected_in'])->toBe(30.0)
            ->and((float) $rows['2026-10-25']['closing_balance'])->toBe(980.0)
            ->and((float) $rows['2026-10-27']['expected_in'])->toBe(100.0)
            ->and((float) $rows['2026-10-27']['security_in_reference'])->toBe(300.0)
            ->and((float) $rows['2026-10-27']['closing_balance'])->toBe(1080.0)
            ->and((float) $rows['2026-10-28']['expected_out'])->toBe(60.0)
            ->and((float) $rows['2026-10-28']['closing_balance'])->toBe(1020.0)
            ->and((float) $rows['2026-10-30']['expected_in'])->toBe(0.0)
            ->and((float) $result->totals['expected_in'])->toBe(130.0)
            ->and((float) $result->totals['expected_out'])->toBe(60.0)
            ->and((float) $result->totals['actual_in'])->toBe(50.0);

        $monthly = $query->execute(marsCashFlowReportContext([...$filters, 'granularity' => 'month']));
        expect($monthly->totalRows)->toBe(1)
            ->and($monthly->rows[0]['period_start'])->toBe('2026-10-24')
            ->and($monthly->rows[0]['period_end'])->toBe('2026-10-30')
            ->and((float) $monthly->rows[0]['opening_balance'])->toBe(900.0)
            ->and((float) $monthly->rows[0]['closing_balance'])->toBe(1020.0);

        $paged = $query->execute(marsCashFlowReportContext($filters, 1, 2));
        expect($paged->totalRows)->toBe(7)
            ->and($paged->rows)->toHaveCount(2)
            ->and($paged->rows[0]['period_start'])->toBe('2026-10-25');

        expect(fn () => $query->execute(marsCashFlowReportContext([
            'as_of' => '2026-10-24', 'date_from' => '2026-10-24', 'date_to' => '2028-10-25',
        ])))->toThrow(DomainException::class);
    });
});

it('does not mix currencies or treat reference checks as available cash', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $now = now();
        $contact = $db->table('contacts')->insertGetId([
            'title' => 'Currency customer '.Str::random(10), 'type' => 'legal',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach ([
            ['EUR', 'debit', 70],
            ['USD', 'credit', 80],
        ] as [$currency, $direction, $amount]) {
            $db->table('contact_transactions')->insert([
                'contact_id' => $contact, 'transaction_type' => 'manual',
                'transaction_date' => '2026-10-20', 'due_date' => '2026-10-26',
                'currency' => $currency, 'direction' => $direction, 'amount' => $amount,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $query = new CashFlowCalendarReportQuery;
        $f = ['as_of' => '2026-10-24', 'date_from' => '2026-10-24',
            'date_to' => '2026-10-26'];

        $result = $query->execute(marsCashFlowReportContext($f));
        $onDue = collect($result->rows)->filter(fn (array $row): bool =>
            $row['period_start'] === '2026-10-26')->keyBy('currency');

        expect((float) $onDue['EUR']['closing_balance'])->toBe(70.0)
            ->and((float) $onDue['USD']['closing_balance'])->toBe(-80.0)
            ->and($onDue['USD']['cash_alert'])->toBe('Nakit açığı')
            ->and($result->totals['expected_in'])->toContain('EUR')
            ->and($result->totals['expected_out'])->toContain('USD');

        $euro = $query->execute(marsCashFlowReportContext([...$f, 'currency' => 'EUR']));
        expect($euro->totalRows)->toBe(3)
            ->and((float) $euro->totals['expected_in'])->toBe(70.0);
    });
});
