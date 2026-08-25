<?php

use App\Modules\Quotes\Pricing\DeterministicTaxCalculator;
use App\Modules\Quotes\Pricing\PriceBasis;
use App\Modules\Quotes\Pricing\TaxCalculationLineInput;
use InvalidArgumentException;

it('calculates net-priced tax with exact six-decimal half-up rounding', function (): void {
    $result = (new DeterministicTaxCalculator)->calculate([
        new TaxCalculationLineInput(
            key: 'line-1',
            quantity: '3',
            unitPrice: '33.333333',
            priceBasis: PriceBasis::Net,
            taxRate: '20',
        ),
    ]);

    $line = $result->lines[0];

    expect($line->baseNet)->toBe('99.999999')
        ->and($line->net)->toBe('99.999999')
        ->and($line->tax)->toBe('20.000000')
        ->and($line->gross)->toBe('119.999999')
        ->and($result->net)->toBe('99.999999')
        ->and($result->tax)->toBe('20.000000')
        ->and($result->gross)->toBe('119.999999');
});

it('normalizes tax-included gross pricing without losing the entered gross total', function (): void {
    $result = (new DeterministicTaxCalculator)->calculate([
        new TaxCalculationLineInput(
            key: 'gross-line',
            quantity: '2',
            unitPrice: '120',
            priceBasis: PriceBasis::Gross,
            taxRate: '20',
        ),
    ]);

    $line = $result->lines[0];

    expect($line->baseNet)->toBe('200.000000')
        ->and($line->net)->toBe('200.000000')
        ->and($line->tax)->toBe('40.000000')
        ->and($line->gross)->toBe('240.000000');
});

it('applies line and document discounts sequentially before tax', function (): void {
    $result = (new DeterministicTaxCalculator)->calculate([
        new TaxCalculationLineInput(
            key: 'discounted',
            quantity: '2',
            unitPrice: '100',
            priceBasis: PriceBasis::Net,
            taxRate: '20',
            lineDiscountRate: '10',
        ),
    ], documentDiscountRate: '5');

    $line = $result->lines[0];

    expect($line->baseNet)->toBe('200.000000')
        ->and($line->lineDiscountNet)->toBe('20.000000')
        ->and($line->documentDiscountNet)->toBe('9.000000')
        ->and($line->net)->toBe('171.000000')
        ->and($line->tax)->toBe('34.200000')
        ->and($line->gross)->toBe('205.200000');
});

it('keeps gross-basis discount normalization internally reconcilable', function (): void {
    $result = (new DeterministicTaxCalculator)->calculate([
        new TaxCalculationLineInput(
            key: 'gross-discount',
            quantity: '1',
            unitPrice: '120',
            priceBasis: PriceBasis::Gross,
            taxRate: '20',
            lineDiscountRate: '10',
        ),
    ], documentDiscountRate: '5');

    $line = $result->lines[0];

    expect($line->baseNet)->toBe('100.000000')
        ->and($line->lineDiscountNet)->toBe('10.000000')
        ->and($line->documentDiscountNet)->toBe('4.500000')
        ->and($line->net)->toBe('85.500000')
        ->and($line->tax)->toBe('17.100000')
        ->and($line->gross)->toBe('102.600000');
});

it('requires a canonical zero-tax reason and forbids it on taxable lines', function (): void {
    $calculator = new DeterministicTaxCalculator;

    expect(fn () => $calculator->calculate([
        new TaxCalculationLineInput(
            key: 'zero-without-reason',
            quantity: '1',
            unitPrice: '50',
            priceBasis: PriceBasis::Net,
            taxRate: '0',
        ),
    ]))->toThrow(InvalidArgumentException::class);

    $zero = $calculator->calculate([
        new TaxCalculationLineInput(
            key: 'zero-with-reason',
            quantity: '1',
            unitPrice: '50',
            priceBasis: PriceBasis::Net,
            taxRate: '0',
            taxZeroReasonCode: ' istisna-301 ',
        ),
    ])->lines[0];

    expect($zero->taxZeroReasonCode)->toBe('ISTISNA-301')
        ->and($zero->tax)->toBe('0.000000')
        ->and($zero->gross)->toBe('50.000000');

    expect(fn () => $calculator->calculate([
        new TaxCalculationLineInput(
            key: 'taxable-with-reason',
            quantity: '1',
            unitPrice: '50',
            priceBasis: PriceBasis::Net,
            taxRate: '20',
            taxZeroReasonCode: '301',
        ),
    ]))->toThrow(InvalidArgumentException::class);
});

it('aggregates mixed net and gross lines deterministically', function (): void {
    $calculator = new DeterministicTaxCalculator;
    $lines = [
        new TaxCalculationLineInput('a', '1', '100', PriceBasis::Net, '20'),
        new TaxCalculationLineInput('b', '1', '120', PriceBasis::Gross, '20'),
        new TaxCalculationLineInput('c', '2', '50', PriceBasis::Net, '0', taxZeroReasonCode: '351'),
    ];

    $first = $calculator->calculate($lines);
    $second = $calculator->calculate($lines);

    expect($first)->toEqual($second)
        ->and($first->baseNet)->toBe('300.000000')
        ->and($first->net)->toBe('300.000000')
        ->and($first->tax)->toBe('40.000000')
        ->and($first->gross)->toBe('340.000000');
});

it('rejects invalid rates quantities duplicate keys and numeric overflow', function (): void {
    $calculator = new DeterministicTaxCalculator;

    expect(fn () => $calculator->calculate([
        new TaxCalculationLineInput('bad-rate', '1', '1', PriceBasis::Net, '100.000001'),
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => $calculator->calculate([
        new TaxCalculationLineInput('bad-qty', '0', '1', PriceBasis::Net, '20'),
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => $calculator->calculate([
        new TaxCalculationLineInput('same', '1', '1', PriceBasis::Net, '20'),
        new TaxCalculationLineInput('same', '1', '1', PriceBasis::Net, '20'),
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => $calculator->calculate([
        new TaxCalculationLineInput(
            key: 'overflow',
            quantity: '99999999999999.999999',
            unitPrice: '99999999999999.999999',
            priceBasis: PriceBasis::Net,
            taxRate: '20',
        ),
    ]))->toThrow(InvalidArgumentException::class);
});
