<?php

use App\Support\Money\Money;

it('preserves four decimal places and computes arithmetic without floats', function (): void {
    $amount = Money::of('0.1000')->plus(Money::of('0.2000'));
    expect($amount->amount)->toBe('0.3000')
        ->and($amount->minus(Money::of('0.0500'))->amount)->toBe('0.2500')
        ->and($amount->times('3')->amount)->toBe('0.9000')
        ->and($amount->percent('10')->amount)->toBe('0.0300');
});

it('does not combine different currencies or equate them', function (): void {
    expect(Money::of('1', 'USD')->equals(Money::of('1', 'TRY')))->toBeFalse();
    expect(fn () => Money::of(1, 'USD')->plus(Money::of(1, 'TRY')))
        ->toThrow(DomainException::class);
    expect(fn () => Money::of(1, 'EUR')->minus(Money::of(1, 'TRY')))
        ->toThrow(DomainException::class);
});

it('handles signed amounts, exact equality and decimal rounding', function (): void {
    expect(Money::of('-0.01')->isNegative())->toBeTrue()
        ->and(Money::of('0.00')->isNegative())->toBeFalse()
        ->and(Money::of('1')->equals(Money::of('1.0000')))->toBeTrue()
        ->and(Money::of('1.235')->round()->amount)->toBe('1.2400')
        ->and((string) Money::of('2'))->toBe('2.0000');
});
