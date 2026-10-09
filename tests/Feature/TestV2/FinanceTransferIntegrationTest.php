<?php

use App\Actions\Finance\PostFinanceTransfer;
use App\Models\Period\BankAccount;
use App\Models\Period\BankMovement;
use App\Models\Period\CashAccount;
use App\Models\Period\CashMovement;

it('v2 finance transfers create equal and opposite immutable ledger entries', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2FIN');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $source = CashAccount::query()->create([
        'code' => 'V2FINA', 'name' => 'Finance Source', 'currency' => 'TRY', 'is_active' => true,
    ]);
    $target = CashAccount::query()->create([
        'code' => 'V2FINB', 'name' => 'Finance Target', 'currency' => 'TRY', 'is_active' => true,
    ]);

    $action = app(PostFinanceTransfer::class);
    $result = $action->handle('cash', $source->id, 'cash', $target->id, '19.4321', '2026-09-01', 'v2-finance-transfer');

    expect($result['source']->direction)->toBe('out')
        ->and($result['target']->direction)->toBe('in')
        ->and($result['source']->amount)->toBe('19.4321')
        ->and($result['target']->amount)->toBe('19.4321')
        ->and($result['source']->group_key)->toBe($result['target']->group_key)
        ->and($source->balance())->toBe('-19.4321')
        ->and($target->balance())->toBe('19.4321')
        ->and(CashMovement::query()->count())->toBe(2);

    $again = $action->handle('cash', $source->id, 'cash', $target->id, '19.4321', '2026-09-01', 'v2-finance-transfer');

    expect(CashMovement::query()->count())->toBe(2)
        ->and($again['source']->id)->toBe($result['source']->id)
        ->and($again['target']->id)->toBe($result['target']->id);
});

it('v2 finance refuses transfer to same account before any ledger mutation', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2FINSAME');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
    $cash = CashAccount::query()->create([
        'code' => 'V2SAM', 'name' => 'Same', 'currency' => 'TRY', 'is_active' => true,
    ]);

    expect(fn () => app(PostFinanceTransfer::class)->handle(
        'cash', $cash->id, 'cash', $cash->id, '1.0000', '2026-09-01', 'v2-finance-same',
    ))->toThrow(DomainException::class);

    expect(CashMovement::query()->count())->toBe(0);
});

it('v2 finance refuses currency mismatch and leaves both ledgers untouched', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2FINFX');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
    $try = CashAccount::query()->create([
        'code' => 'V2TRY', 'name' => 'TRY', 'currency' => 'TRY', 'is_active' => true,
    ]);
    $usd = CashAccount::query()->create([
        'code' => 'V2USD', 'name' => 'USD', 'currency' => 'USD', 'is_active' => true,
    ]);

    expect(fn () => app(PostFinanceTransfer::class)->handle(
        'cash', $try->id, 'cash', $usd->id, '1.0000', '2026-09-01', 'v2-finance-fx',
    ))->toThrow(DomainException::class);

    expect(CashMovement::query()->count())->toBe(0);
});

it('v2 finance supports balanced cash-to-bank movements without statement-origin mutation', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2FINBANK');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $cash = CashAccount::query()->create([
        'code' => 'V2CASH', 'name' => 'Cash', 'currency' => 'TRY', 'is_active' => true,
    ]);
    $bank = BankAccount::query()->create([
        'code' => 'V2BANK', 'bank_name' => 'Test Bank', 'account_name' => 'Test IBAN',
        'currency' => 'TRY', 'is_active' => true,
    ]);

    $result = app(PostFinanceTransfer::class)->handle(
        'cash', $cash->id, 'bank', $bank->id, '22.0050', '2026-09-01', 'v2-cash-to-bank',
    );

    expect($result['source']->amount)->toBe('22.0050')
        ->and($result['target']->amount)->toBe('22.0050')
        ->and($result['source']->group_key)->toBe($result['target']->group_key)
        ->and($cash->balance())->toBe('-22.0050')
        ->and($bank->bookBalance())->toBe('22.0050')
        ->and(BankMovement::query()->where('origin', 'book')->count())->toBe(1)
        ->and(BankMovement::query()->where('origin', 'statement')->count())->toBe(0);
});
