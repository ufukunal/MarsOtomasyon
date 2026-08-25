<?php

use App\Modules\Accounts\Ledger\AccountBalance;
use App\Modules\Accounts\Ledger\AccountBalanceState;
use InvalidArgumentException;

it('formats a positive signed balance as debtor without binary float arithmetic', function (): void {
    $balance = new AccountBalance('001250.5', 'TRY');

    expect($balance->signedAmount)->toBe('1250.500000')
        ->and($balance->state())->toBe(AccountBalanceState::Debtor)
        ->and($balance->state()->label())->toBe('Borçlu')
        ->and($balance->formatted())->toBe('1.250,50 TRY')
        ->and($balance->debitDisplay())->toBe('1.250,50')
        ->and($balance->creditDisplay())->toBe('—');
});

it('formats a negative signed balance as creditor and preserves meaningful precision', function (): void {
    $balance = new AccountBalance('-25.125', 'EUR');

    expect($balance->signedAmount)->toBe('-25.125000')
        ->and($balance->state())->toBe(AccountBalanceState::Creditor)
        ->and($balance->state()->label())->toBe('Alacaklı')
        ->and($balance->formatted())->toBe('25,125 EUR')
        ->and($balance->debitDisplay())->toBe('—')
        ->and($balance->creditDisplay())->toBe('25,125');
});

it('normalizes signed zero to the neutral balance state', function (): void {
    $balance = new AccountBalance('-0.000000', 'TRY');

    expect($balance->signedAmount)->toBe('0.000000')
        ->and($balance->state())->toBe(AccountBalanceState::Zero)
        ->and($balance->state()->label())->toBe('Bakiye Yok')
        ->and($balance->formatted())->toBe('0,00 TRY');
});

it('rejects malformed balances and currency codes', function (): void {
    expect(fn () => new AccountBalance('1.0000001', 'TRY'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new AccountBalance('1', 'try'))->toThrow(InvalidArgumentException::class);
});
