<?php

use App\Models\Period\ProductionOrder;

it('calculates remaining production quantity after partial completion and cancellation', function (): void {
    $order = new ProductionOrder([
        'planned_quantity' => '20',
        'completed_quantity' => '7.5',
        'cancelled_quantity' => '2.5',
    ]);
    expect($order->remainingQuantity())->toBe('10.000');
});

it('shows over-completion instead of silently clamping it to zero', function (): void {
    $order = new ProductionOrder([
        'planned_quantity' => '5',
        'completed_quantity' => '6',
        'cancelled_quantity' => '0',
    ]);
    expect($order->remainingQuantity())->toBe('-1.000');
});
