<?php

use App\Actions\Finance\ImportBankStatement;
use App\Exceptions\PeriodYearMismatchException;
use App\Models\Period\BankAccount;
use App\Models\Period\BankMovement;

it('v2 bank statement import persists isolated statement-origin entries without changing book balance', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2BANKIMP');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $account = BankAccount::query()->create([
        'code' => 'V2STMT1', 'bank_name' => 'Fake bank', 'account_name' => 'Test account',
        'currency' => 'TRY', 'is_active' => true,
    ]);
    $file = tempnam(sys_get_temp_dir(), 'mars-v2-stmt-');
    try {
        file_put_contents($file, "Date;Description;Credit;Debit;Reference\n2026-09-01;Test receipt;120,25;;TX01\n");
        $action = app(ImportBankStatement::class);
        $result = $action->handle($account->id, $file, 'csv', 'v2-bank-import-A');

        expect($result['imported'])->toBe(1)
            ->and($result['duplicates'])->toBe(0)
            ->and(strlen($result['checksum']))->toBe(64)
            ->and(BankMovement::query()->where('origin', 'statement')->count())->toBe(1)
            ->and(BankMovement::query()->where('origin', 'book')->count())->toBe(0)
            ->and($account->bookBalance())->toBe('0.0000')
            ->and($account->unreconciledStatementCount())->toBe(1);

        $replayed = $action->handle($account->id, $file, 'csv', 'v2-bank-import-A');
        expect($replayed['rows'])->toBe($result['rows'])
            ->and(BankMovement::query()->count())->toBe(1);

        $duplicate = $action->handle($account->id, $file, 'csv', 'v2-bank-import-B');
        expect($duplicate['imported'])->toBe(0)
            ->and($duplicate['duplicates'])->toBe(1)
            ->and(BankMovement::query()->count())->toBe(1);
    } finally {
        @unlink($file);
    }
});

it('v2 bank statement import refuses different-year movements and leaves no ledger residue', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2BANKYEAR');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
    $account = BankAccount::query()->create([
        'code' => 'V2STMT2', 'bank_name' => 'Fake bank', 'account_name' => 'Test account',
        'currency' => 'TRY', 'is_active' => true,
    ]);

    $file = tempnam(sys_get_temp_dir(), 'mars-v2-stmt-');
    try {
        file_put_contents($file, "Date;Description;Credit;Debit;Reference\n2025-09-01;Wrong year;90,00;;WRONG\n");

        expect(fn () => app(ImportBankStatement::class)->handle($account->id, $file, 'csv', 'v2-bank-wrong-year'))
            ->toThrow(PeriodYearMismatchException::class);

        expect(BankMovement::query()->count())->toBe(0)
            ->and($account->bookBalance())->toBe('0.0000');
    } finally {
        @unlink($file);
    }
});
