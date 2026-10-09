<?php

use App\Models\Period\ProductionOrder;

it('v2 production remaining quantity subtracts completed and cancelled quantities precisely', function () {
    $order = new ProductionOrder([
        'planned_quantity' => '10.000',
        'completed_quantity' => '3.125',
        'cancelled_quantity' => '2.375',
    ]);

    expect($order->remainingQuantity())->toBe('4.500');
});

it('v2 production does not silently hide planned output overconsumption', function () {
    $order = new ProductionOrder([
        'planned_quantity' => '5.000',
        'completed_quantity' => '5.000',
        'cancelled_quantity' => '1.000',
    ]);

    expect($order->remainingQuantity())->toBe('-1.000');
});
