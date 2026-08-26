<?php

namespace App\Modules\Dispatches\Actions;

final readonly class DispatchLineData
{
    public function __construct(
        public int $salesOrderLineId,
        public string $quantity,
        public ?int $warehouseId,
        public ?int $locationId,
    ) {}
}
