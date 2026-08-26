<?php

namespace App\Modules\Dispatches\Actions;

final readonly class DispatchDraftData
{
    /** @param list<DispatchLineData> $lines */
    public function __construct(
        public int $salesOrderId,
        public int $sourceAddressId,
        public string $dispatchDate,
        public ?string $carrierName,
        public ?string $carrierService,
        public ?string $trackingNumber,
        public ?string $note,
        public array $lines,
    ) {}
}
