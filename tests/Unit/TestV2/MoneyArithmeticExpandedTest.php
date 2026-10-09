<?php

use App\Support\Money\Money;

it('v2 money adds and subtracts decimal amounts without binary floating point drift', function () {
    $sum = Money::of('0.1000')->plus(Money::of('0.2000'));
    expect($sum->amount)->toBe('0.3000')
        ->and($sum->minus(Money::of('0.3000'))->amount)->toBe('0.0000');
});

it('v2 money keeps four decimal digits for multiplied quantities', function () {
    expect(Money::of('3.1250')->times('2.500')->amount)->toBe('7.8125');
});

it('v2 money computes 20 percent from an exact one-thousandth unit value', function () {
    expect(Money::of('1.0000')->percent('20.0000')->amount)->toBe('0.2000');
});

it('v2 money equality checks currency and exact value not string formatting', function () {
    expect(Money::of('1')->equals(Money::of('1.0000')))->toBeTrue()
        ->and(Money::of('1', 'TRY')->equals(Money::of('1', 'EUR')))->toBeFalse();
});

it('v2 money refuses arithmetic between incompatible currencies', function () {
    expect(fn () => Money::of('10', 'TRY')->plus(Money::of('2', 'USD')))
        ->toThrow(DomainException::class);
});

it('v2 money positive and negative values are distinguishable', function () {
    expect(Money::of('-0.0001')->isNegative())->toBeTrue()
        ->and(Money::of('0')->isNegative())->toBeFalse();
});

it('v2 money rounds positive and negative midpoint away from zero', function () {
    expect(Money::of('1.2350')->round(2)->amount)->toBe('1.2400')
        ->and(Money::of('-1.2350')->round(2)->amount)->toBe('-1.2400');
});
