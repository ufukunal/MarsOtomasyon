<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

function marsCashBankRejectsSql(callable $action): bool
{
    $db = DB::connection('period');
    $db->beginTransaction();
    $rejected = false;

    try {
        $action($db);
    } catch (QueryException) {
        $rejected = true;
    } finally {
        $db->rollBack();
    }

    return $rejected;
}

it('accepts a valid cash receipt and rejects zero, negative and invalid directions', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $id = $db->table('cash_accounts')->insertGetId([
            'code' => 'V4-'.Str::random(10),
            'name' => 'Disposable cash desk',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = [
            'cash_account_id' => $id,
            'movement_date' => '2026-10-10',
            'direction' => 'in',
            'amount' => '20.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $db->table('cash_movements')->insert($row);

        foreach ([
            ['amount' => '0'],
            ['amount' => '-1.0000'],
            ['direction' => 'reversal'],
        ] as $bad) {
            expect(marsCashBankRejectsSql(fn ($connection) => $connection->table('cash_movements')
                ->insert(array_merge($row, $bad))))->toBeTrue();
        }

        expect($db->table('cash_movements')->where('cash_account_id', $id)->count())->toBe(1);
    });
});

it('rejects malformed bank movement directions without altering the bank ledger', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $bankId = $db->table('bank_accounts')->insertGetId([
            'code' => 'B-'.Str::random(9),
            'bank_name' => 'Disposable bank',
            'account_name' => 'V4 Fixture',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = [
            'bank_account_id' => $bankId,
            'movement_date' => '2026-10-10',
            'direction' => 'sideways',
            'amount' => '5.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        expect(marsCashBankRejectsSql(fn ($connection) => $connection->table('bank_movements')
            ->insert($row)))->toBeTrue();
        expect($db->table('bank_movements')->where('bank_account_id', $bankId)->count())->toBe(0);
    });
});
