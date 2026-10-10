<?php

use App\Actions\Documents\CalculateDocumentTotals;

it('calculates subtotal VAT and grand total for sales and purchase line shapes', function (): void {
    $totals = app(CalculateDocumentTotals::class)->handle([
        ['quantity' => '2', 'unit_price' => '10', 'vat_rate' => '20'],
        ['quantity' => '1', 'unit_price' => '30', 'vat_rate' => '0'],
    ]);
    expect($totals->subtotal)->toBe('50.0000')
        ->and($totals->taxBase)->toBe('50.0000')
        ->and($totals->vatAmount)->toBe('4.0000')
        ->and($totals->grandTotal)->toBe('54.0000')
        ->and($totals->lines)->toHaveCount(2);
});

it('applies line and document discounts without losing VAT allocation', function (): void {
    $totals = app(CalculateDocumentTotals::class)->handle([
        ['quantity' => '1', 'unit_price' => '100', 'line_discount_rate' => '10', 'vat_rate' => '20'],
    ], '10');
    expect($totals->subtotal)->toBe('90.0000')
        ->and($totals->discountAmount)->toBe('9.0000')
        ->and($totals->taxBase)->toBe('81.0000')
        ->and($totals->vatAmount)->toBe('16.2000')
        ->and($totals->grandTotal)->toBe('97.2000');
});

it('refuses invalid line quantities, prices, VAT and inconsistent discounts', function (array $lines, string $discountRate): void {
    expect(fn () => app(CalculateDocumentTotals::class)->handle($lines, $discountRate))
        ->toThrow(DomainException::class);
})->with([
    'empty' => [[], '0'],
    'zero quantity' => [[['quantity' => '0', 'unit_price' => '10']], '0'],
    'negative price' => [[['quantity' => '1', 'unit_price' => '-1']], '0'],
    'negative VAT' => [[['quantity' => '1', 'unit_price' => '10', 'vat_rate' => '-1']], '0'],
    'line discount too large' => [[['quantity' => '1', 'unit_price' => '10', 'line_discount_amount' => '11']], '0'],
    'conflicting line discounts' => [[['quantity' => '1', 'unit_price' => '10', 'line_discount_rate' => '10', 'line_discount_amount' => '3']], '0'],
    'document discount > 100%' => [[['quantity' => '1', 'unit_price' => '10']], '101'],
]);

it('permits zero-priced lines without manufacturing a VAT amount', function (): void {
    $result = app(CalculateDocumentTotals::class)->handle([
        ['quantity' => '1.000', 'unit_price' => '0', 'vat_rate' => '20'],
    ]);
    expect($result->grandTotal)->toBe('0.0000')
        ->and($result->vatAmount)->toBe('0.0000');
});
