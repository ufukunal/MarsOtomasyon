<?php

use App\Support\Money\Money;
use Tests\ManualAudit\Support\AuditSource;

test('NUM-126 finance ve business calculation kodunda float cast bulunmaz', function () {
    expect(AuditSource::grep(
        '/(?:\(float\)|floatval\s*\()[^;\n]*(?:amount|price|cost|total|quantity|rate)/i',
        ['app/Actions/Finance', 'app/Actions/Documents', 'app/Actions/Stock', 'app/Support/Money'],
    ))->toBe([]);
});

test('NUM-127 Money aritmetiği BCMath kullanır', function () {
    $source = AuditSource::read('app/Support/Money/Money.php');

    foreach (['bcadd(', 'bcsub(', 'bcmul(', 'bcdiv(', 'bccomp('] as $needle) {
        expect($source)->toContain($needle);
    }
});

test('NUM-128 quantity hesaplarında 3 scale korunur', function () {
    $combined = AuditSource::read('app/Actions/Stock/RecordStockMovement.php')
        .AuditSource::read('app/Actions/Stock/ReserveStock.php');

    expect($combined)->toMatch('/bc(?:add|sub|comp|mul|div)\([^;]+,\s*3\)/');
});

test('NUM-129 exchange rate hesaplarında decimal string ve BCMath kullanılır', function () {
    $offenders = AuditSource::grep(
        '/(?:exchange_rate|exchangeRate)[^;\n]*(?:\*|\/)\s*\$|\$(?:exchange_rate|exchangeRate)\s*(?:\*|\/)/i',
        ['app/Actions', 'app/Support'],
    );

    expect($offenders)->toBe([]);
});

test('NUM-130 VAT hesapları decimal string precision ile yürür', function () {
    $source = AuditSource::read('app/Actions/Documents/CalculateDocumentTotals.php');

    expect($source)->toMatch('/bc(?:mul|div|add|sub|comp)/');
});

test('NUM-131 discount hesapları native float yerine BCMath kullanır', function () {
    $source = AuditSource::read('app/Actions/Documents/CalculateDocumentTotals.php');

    expect($source)->toMatch('/discount[\s\S]*bc(?:mul|div|sub|add)/i');
});

test('NUM-132 TRY ve para tutarı normalize edilirken dört decimal korunur', function () {
    expect((string) Money::of('12.34567', 'TRY'))->toBe('12.3456')
        ->and((string) Money::of(12, 'TRY'))->toBe('12.0000');
});

test('NUM-133 moving average calculation BCMath kullanır', function () {
    $source = AuditSource::read('app/Actions/Stock/UpdateMovingAverage.php');

    expect($source)->toMatch('/bc(?:add|sub|mul|div|comp)/');
});

test('NUM-134 negative zero para değerine dönüşmez', function () {
    expect((string) Money::of('-0.00001', 'TRY'))->toBe('0.0000');
});

test('NUM-135 büyük decimal değerler floata düşmeden hesaplanır', function () {
    $a = Money::of('999999999999.9999', 'TRY');
    $b = Money::of('0.0001', 'TRY');

    expect((string) $a->plus($b))->toBe('1000000000000.0000');
});
