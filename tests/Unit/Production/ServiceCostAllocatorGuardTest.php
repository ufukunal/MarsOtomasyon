<?php

use App\Models\Period\ProductionOrder;
use App\Support\Production\ProductionServiceCostAllocator;

it('does not allocate outsourced service costs to an internal production order', function (): void {
    $service = (new ReflectionClass(ProductionServiceCostAllocator::class))->newInstanceWithoutConstructor();
    $order = new ProductionOrder(['production_type' => 'internal']);
    expect($service->prepareNewCompletion($order, '3', '2026-10-10'))
        ->toBe(['service_cost' => '0.0000', 'shares' => []]);
    $service->lockRelevantCompletions($order);
    expect(true)->toBeTrue();
});
