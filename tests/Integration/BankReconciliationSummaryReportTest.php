<?php

use App\Support\Reporting\Queries\BankReconciliationSummaryReportQuery;
use App\Support\Reporting\ReportExecutionContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

it('groups statement reconciliation by bank account and preserves currency-separated unmatched totals', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $code = Str::random(9);
        $try = $db->table('bank_accounts')->insertGetId([
            'code' => 'TR-'.$code, 'bank_name' => 'Bank',
            'account_name' => 'Current TRY', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $eur = $db->table('bank_accounts')->insertGetId([
            'code' => 'EU-'.$code, 'bank_name' => 'Bank',
            'account_name' => 'Current EUR', 'currency' => 'EUR',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $bookId = $db->table('bank_movements')->insertGetId([
            'bank_account_id' => $try,
            'movement_date' => '2026-09-10', 'direction' => 'in',
            'amount' => 100, 'origin' => 'book', 'movement_type' => 'manual',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            [$try, 'in', 100, $bookId, 'TR-MATCH'],
            [$try, 'out', 80, null, 'TR-UNMATCH'],
            [$eur, 'in', 20, null, 'EU-UNMATCH'],
        ] as [$account, $direction, $amount, $matched, $fingerprint]) {
            $db->table('bank_movements')->insert([
                'bank_account_id' => $account,
                'movement_date' => '2026-09-11',
                'direction' => $direction, 'amount' => $amount,
                'origin' => 'statement', 'movement_type' => 'statement',
                'reconciled_movement_id' => $matched,
                'statement_fingerprint' => hash('sha256', $code.$fingerprint),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $query = new BankReconciliationSummaryReportQuery;
        $definition = $query->definition();
        $run = fn (array $filters) => $query->execute(new ReportExecutionContext(
            filters: $filters,
            columns: $definition->defaultColumnKeys(false),
            sort: $definition->defaultSort,
            totalKeys: array_map(fn ($total): string => $total->key, $definition->totals),
            limit: 20, offset: 0, canViewCost: false,
        ));
        $report = $run(['date_from' => '2026-09-01', 'date_to' => '2026-09-30']);

        expect($report->totalRows)->toBe(2);
        $rows = collect($report->rows)->keyBy('currency');
        expect((int) $rows['TRY']['statement_count'])->toBe(2)
            ->and((int) $rows['TRY']['matched_count'])->toBe(1)
            ->and((int) $rows['TRY']['unmatched_count'])->toBe(1)
            ->and((float) $rows['TRY']['matching_rate'])->toBe(50.0)
            ->and((float) $rows['TRY']['unmatched_out'])->toBe(80.0)
            ->and((int) $rows['EUR']['unmatched_count'])->toBe(1)
            ->and((float) $rows['EUR']['unmatched_in'])->toBe(20.0)
            ->and($report->totals['unmatched_in'])->toContain('EUR')
            ->and($report->totals['unmatched_out'])->toContain('TRY');

        $tryOnly = $run(['bank_account_id' => $try, 'only_unmatched' => true]);
        expect($tryOnly->totalRows)->toBe(1)
            ->and((float) $tryOnly->totals['unmatched_out'])->toBe(80.0);

        $future = $run(['date_from' => '2026-10-01']);
        expect($future->totalRows)->toBe(0);
    });
});
