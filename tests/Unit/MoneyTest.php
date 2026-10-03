<?php

use App\Support\Money\Money;
use DomainException;

it('parayı dört ondalıkla ve BCMath ile taşır', function () {
    expect(Money::of('10.12555')->amount)->toBe('10.1255')
        ->and((string) Money::of('10.0000')->plus(Money::of('2.3456')))->toBe('12.3456')
        ->and((string) Money::of('10')->percent('20'))->toBe('2.0000');
});

it('yarım değerleri para işareti korunarak yuvarlar', function () {
    expect(Money::of('1.2350')->round(2)->amount)->toBe('1.2400')
        ->and(Money::of('-1.2350')->round(2)->amount)->toBe('-1.2400');
});

it('farklı para birimlerinin toplanmasına izin vermez', function () {
    expect(fn () => Money::of('1', 'TRY')->plus(Money::of('1', 'USD')))
        ->toThrow(DomainException::class);
});
