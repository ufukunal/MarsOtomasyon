<?php

use App\Actions\Purchases\ThreeWayMatchSupplierInvoice;

it('calculates signed invoice-to-order price variances with fixed-scale arithmetic', function (string $order, string $invoice, string $expected): void {
    $action = (new ReflectionClass(ThreeWayMatchSupplierInvoice::class))->newInstanceWithoutConstructor();
    $variance = new ReflectionMethod(ThreeWayMatchSupplierInvoice::class, 'priceVariance');

    expect($variance->invoke($action, $order, $invoice))->toBe($expected);
})->with([
    'equal' => ['100', '100', '0.0000'],
    'increase' => ['100', '125', '25.0000'],
    'decrease' => ['100', '75', '-25.0000'],
    'zero both' => ['0', '0', '0.0000'],
    'no reference' => ['0', '10', '100.0000'],
    'fractional' => ['40', '41', '2.5000'],
]);
