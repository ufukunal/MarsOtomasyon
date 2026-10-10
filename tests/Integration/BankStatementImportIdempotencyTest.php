<?php

use App\Actions\Finance\ImportBankStatement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('deduplicates bank CSV transactions by fingerprint and never creates two statement ledger entries', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        $fixture = tempnam(sys_get_temp_dir(), 'mars-bank-v4-');
        if ($fixture === false) {
            throw new RuntimeException('Cannot create isolated statement fixture.');
        }

        try {
            $bankId = DB::connection('period')->table('bank_accounts')->insertGetId([
                'code' => 'BK-'.Str::random(10),
                'bank_name' => 'V4 Bank',
                'account_name' => 'Disposable Test',
                'currency' => 'TRY',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            file_put_contents($fixture, "date;description;credit;debit\n2026-10-10;Bank customer payment;125.00;0\n");
            $import = app(ImportBankStatement::class);

            $first = $import->handle($bankId, $fixture, 'csv', 'v4-'.Str::random(18));
            $second = $import->handle($bankId, $fixture, 'csv', 'v4-'.Str::random(18));

            expect($first['imported'])->toBe(1)
                ->and($second['duplicates'])->toBe(1);
            expect(DB::connection('period')->table('bank_movements')
                ->where('bank_account_id', $bankId)->where('origin', 'statement')->count())->toBe(1);
        } finally {
            unlink($fixture);
            AuthorizedPeriod::logout();
        }
    });
});
