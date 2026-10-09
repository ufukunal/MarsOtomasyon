<?php

use App\Actions\Finance\PostFinanceTransfer;
use App\Models\Period\CashAccount;
use App\Models\Period\CashMovement;

beforeEach(function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2FINVAL');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
});

it('v2 financial transfer refuses zero and negative amounts before ledger entries', function (string $amount) {
    $source = CashAccount::query()->create(['code' => 'V2-VS', 'name' => 'Source', 'currency' => 'TRY', 'is_active' => true]);
    $target = CashAccount::query()->create(['code' => 'V2-VT', 'name' => 'Target', 'currency' => 'TRY', 'is_active' => true]);
    expect(fn () => app(PostFinanceTransfer::class)->handle('cash', $source->id, 'cash', $target->id, $amount, '2026-09-01', 'v2-val-'.str_replace('.', '-', $amount)))
        ->toThrow(DomainException::class);
    expect(CashMovement::query()->count())->toBe(0);
})->with(['0.0000', '-0.0001']);

it('v2 financial transfer refuses unsupported source type without ledger mutation', function () {
    $target = CashAccount::query()->create(['code' => 'V2-VT', 'name' => 'Target', 'currency' => 'TRY', 'is_active' => true]);
    expect(fn () => app(PostFinanceTransfer::class)->handle('external', 33, 'cash', $target->id, '5.0000', '2026-09-01', 'v2-invalid-source'))
        ->toThrow(DomainException::class);
    expect(CashMovement::query()->count())->toBe(0);
});