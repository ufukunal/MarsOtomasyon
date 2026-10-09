<?php

use App\Actions\Finance\PostFinanceTransfer;
use App\Actions\Finance\ReverseFinanceTransfer;
use App\Models\Period\CashAccount;
use App\Models\Period\CashMovement;

beforeEach(function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2FINREV');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
});

function v2ReversalAccounts(): array
{
    return [
        CashAccount::query()->create(['code' => 'V2-REVS', 'name' => 'Source', 'currency' => 'TRY', 'is_active' => true]),
        CashAccount::query()->create(['code' => 'V2-REVT', 'name' => 'Target', 'currency' => 'TRY', 'is_active' => true]),
    ];
}

it('v2 finance reversal posts two opposite immutable movements and restores balances to zero', function () {
    [$source, $target] = v2ReversalAccounts();
    $posted = app(PostFinanceTransfer::class)->handle('cash', $source->id, 'cash', $target->id, '15.0000', '2026-09-01', 'v2-reverse-initial');
    $reverse = app(ReverseFinanceTransfer::class)->handle($posted['source']->group_key, '2026-09-02', 'Correction', 'v2-reverse-once');

    expect($reverse)->toHaveCount(2)
        ->and(CashMovement::query()->count())->toBe(4)
        ->and($source->balance())->toBe('0.0000')
        ->and($target->balance())->toBe('0.0000')
        ->and(CashMovement::query()->whereNotNull('reversal_of_id')->count())->toBe(2);
});

it('v2 finance reversal refuses an unknown transfer pair without creating movements', function () {
    v2ReversalAccounts();
    expect(fn () => app(ReverseFinanceTransfer::class)->handle('no-such-group', '2026-09-01', 'Correction', 'v2-rev-absent'))
        ->toThrow(DomainException::class);
    expect(CashMovement::query()->count())->toBe(0);
});

it('v2 finance reversal rejects missing justification without mutating ledger', function () {
    [$source, $target] = v2ReversalAccounts();
    $posted = app(PostFinanceTransfer::class)->handle('cash', $source->id, 'cash', $target->id, '15.0000', '2026-09-01', 'v2-rev-prior');
    expect(fn () => app(ReverseFinanceTransfer::class)->handle($posted['source']->group_key, '2026-09-02', '  ', 'v2-rev-no-reason'))
        ->toThrow(DomainException::class);
    expect(CashMovement::query()->count())->toBe(2);
});

it('v2 finance reversal idempotency returns same entries on repeated request', function () {
    [$source, $target] = v2ReversalAccounts();
    $posted = app(PostFinanceTransfer::class)->handle('cash', $source->id, 'cash', $target->id, '15.0000', '2026-09-01', 'v2-rev-original');
    $action = app(ReverseFinanceTransfer::class);
    $once = $action->handle($posted['source']->group_key, '2026-09-02', 'Correction', 'v2-rev-idem');
    $again = $action->handle($posted['source']->group_key, '2026-09-02', 'Correction', 'v2-rev-idem');
    expect(array_map(fn ($item): int => $item->id, $once))
        ->toBe(array_map(fn ($item): int => $item->id, $again))
        ->and(CashMovement::query()->count())->toBe(4);
});