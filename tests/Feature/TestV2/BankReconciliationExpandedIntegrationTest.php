<?php

use App\Actions\Finance\ReconcileBankStatement;
use App\Models\Period\BankAccount;
use App\Models\Period\BankMovement;

beforeEach(function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2BANKREC');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
});

function v2StatementFixtures(string $currency = 'TRY'): array
{
    $account = BankAccount::query()->create([
        'code' => 'V2-RECON', 'bank_name' => 'Mock bank', 'account_name' => 'Statement account',
        'currency' => $currency, 'is_active' => true,
    ]);
    $statement = BankMovement::query()->create([
        'bank_account_id' => $account->id, 'movement_date' => '2026-09-01',
        'direction' => 'in', 'movement_type' => 'statement', 'amount' => '20.5000',
        'origin' => 'statement', 'statement_fingerprint' => hash('sha256', 'v2-test-statement'),
        'statement_description' => 'Inbound transfer',
    ]);

    return [$account, $statement];
}

it('v2 reconciliation rejects unknown processing mode without modifying statement', function () {
    [$account, $statement] = v2StatementFixtures();
    expect(fn () => app(ReconcileBankStatement::class)->handle($statement->id, 'guess', 'v2-unknown-mode'))
        ->toThrow(DomainException::class);
    expect($statement->refresh()->reconciled_movement_id)->toBeNull();
});

it('v2 reconciliation requires a book movement reference in existing-match mode', function () {
    [$account, $statement] = v2StatementFixtures();
    expect(fn () => app(ReconcileBankStatement::class)->handle($statement->id, 'existing', 'v2-without-book'))
        ->toThrow(DomainException::class);
    expect($statement->refresh()->reconciled_movement_id)->toBeNull();
});

it('v2 reconciliation rejects a book movement with an incompatible amount', function () {
    [$account, $statement] = v2StatementFixtures();
    $book = BankMovement::query()->create([
        'bank_account_id' => $account->id, 'movement_date' => '2026-09-01',
        'direction' => 'in', 'movement_type' => 'manual', 'amount' => '19.5000', 'origin' => 'book',
    ]);
    expect(fn () => app(ReconcileBankStatement::class)->handle($statement->id, 'existing', 'v2-wrong-book', $book->id))
        ->toThrow(DomainException::class);
    expect($statement->refresh()->reconciled_movement_id)->toBeNull();
});

it('v2 reconciliation matches a statement and a compatible book movement exactly once', function () {
    [$account, $statement] = v2StatementFixtures();
    $book = BankMovement::query()->create([
        'bank_account_id' => $account->id, 'movement_date' => '2026-09-01',
        'direction' => 'in', 'movement_type' => 'manual', 'amount' => '20.5000', 'origin' => 'book',
    ]);
    $result = app(ReconcileBankStatement::class)->handle($statement->id, 'existing', 'v2-match-once', $book->id);
    expect($result->reconciled_movement_id)->toBe($book->id)
        ->and(BankMovement::query()->count())->toBe(2)
        ->and($account->unreconciledStatementCount())->toBe(0);
});

it('v2 foreign-currency statement cannot create a synthetic TRY book movement', function () {
    [$account, $statement] = v2StatementFixtures('USD');
    expect(fn () => app(ReconcileBankStatement::class)->handle($statement->id, 'create', 'v2-fx-block'))
        ->toThrow(DomainException::class);
    expect(BankMovement::query()->count())->toBe(1)
        ->and($statement->refresh()->reconciled_movement_id)->toBeNull();
});
