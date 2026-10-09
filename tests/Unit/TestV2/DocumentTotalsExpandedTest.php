<?php

use App\Actions\Documents\CalculateDocumentTotals;

it('v2 document calculates VAT from exact quantity and unit price', function () {
    $totals = (new CalculateDocumentTotals)->handle([[
        'quantity' => '2.000', 'unit_price' => '100.0000', 'vat_rate' => '20.0000',
    ]]);
    expect($totals->subtotal)->toBe('200.0000')
        ->and($totals->taxBase)->toBe('200.0000')
        ->and($totals->vatAmount)->toBe('40.0000')
        ->and($totals->grandTotal)->toBe('240.0000');
});

it('v2 document applies document discount before VAT without changing gross subtotal', function () {
    $totals = (new CalculateDocumentTotals)->handle([[
        'quantity' => '2.000', 'unit_price' => '100.0000', 'vat_rate' => '20.0000',
    ]], '10');
    expect($totals->subtotal)->toBe('200.0000')
        ->and($totals->discountAmount)->toBe('20.0000')
        ->and($totals->taxBase)->toBe('180.0000')
        ->and($totals->vatAmount)->toBe('36.0000')
        ->and($totals->grandTotal)->toBe('216.0000');
});

it('v2 document applies line discount before VAT', function () {
    $totals = (new CalculateDocumentTotals)->handle([[
        'quantity' => '1.000', 'unit_price' => '100.0000',
        'line_discount_rate' => '10', 'vat_rate' => '20',
    ]]);
    expect($totals->subtotal)->toBe('90.0000')
        ->and($totals->vatAmount)->toBe('18.0000')
        ->and($totals->grandTotal)->toBe('108.0000');
});

it('v2 document allocates discount proportionally across different VAT rates', function () {
    $totals = (new CalculateDocumentTotals)->handle([
        ['quantity' => '1', 'unit_price' => '100', 'vat_rate' => '0'],
        ['quantity' => '1', 'unit_price' => '100', 'vat_rate' => '20'],
    ], '10');
    expect($totals->subtotal)->toBe('200.0000')
        ->and($totals->taxBase)->toBe('180.0000')
        ->and($totals->vatAmount)->toBe('18.0000')
        ->and($totals->grandTotal)->toBe('198.0000');
});

it('v2 document totals maintain the tax-base plus VAT plus rounding invariant', function () {
    $totals = (new CalculateDocumentTotals)->handle([
        ['quantity' => '3.333', 'unit_price' => '9.9999', 'vat_rate' => '20'],
        ['quantity' => '1.111', 'unit_price' => '7.7777', 'vat_rate' => '10'],
    ], '5');
    expect(bcadd(bcadd($totals->taxBase, $totals->vatAmount, 4), $totals->roundingDifference, 4))
        ->toBe($totals->grandTotal);
});

it('v2 document rejects zero and negative quantities or negative money values', function (array $line) {
    expect(fn () => (new CalculateDocumentTotals)->handle([$line]))
        ->toThrow(DomainException::class);
})->with([
    [['quantity' => '0.000', 'unit_price' => '10', 'vat_rate' => '20']],
    [['quantity' => '-1.000', 'unit_price' => '10', 'vat_rate' => '20']],
    [['quantity' => '1.000', 'unit_price' => '-10', 'vat_rate' => '20']],
    [['quantity' => '1.000', 'unit_price' => '10', 'vat_rate' => '-20']],
]);

it('v2 document refuses empty line calculation', function () {
    expect(fn () => (new CalculateDocumentTotals)->handle([]))->toThrow(DomainException::class);
});

it('v2 document refuses conflicting absolute and percent discounts', function () {
    expect(fn () => (new CalculateDocumentTotals)->handle([
        ['quantity' => '1', 'unit_price' => '100', 'vat_rate' => '20'],
    ], '10', '12'))->toThrow(DomainException::class);
});

it('v2 document refuses discount greater than 100 percent', function () {
    expect(fn () => (new CalculateDocumentTotals)->handle([
        ['quantity' => '1', 'unit_price' => '100', 'vat_rate' => '20'],
    ], '101'))->toThrow(DomainException::class);
});

it('v2 document refuses line discount exceeding its gross value', function () {
    expect(fn () => (new CalculateDocumentTotals)->handle([
        ['quantity' => '1', 'unit_price' => '100', 'vat_rate' => '20', 'line_discount_amount' => '100.0001'],
    ]))->toThrow(DomainException::class);
});
