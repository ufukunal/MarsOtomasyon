<?php

namespace App\DataObjects\Periods;

final readonly class PeriodCarryPreview
{
    /**
     * @param list<array{key:string,status:string,message:string,count?:int}> $checks
     * @param array<string,mixed> $summary
     * @param list<array<string,mixed>> $salesOrders
     * @param list<array<string,mixed>> $purchaseOrders
     * @param list<array<string,mixed>> $importFiles
     * @param list<array<string,mixed>> $quarantine
     */
    public function __construct(
        public int $sourcePeriodId,
        public int $sourceYear,
        public int $targetYear,
        public ?int $targetPeriodId,
        public bool $canCarry,
        public array $checks,
        public array $summary,
        public array $salesOrders,
        public array $purchaseOrders,
        public array $importFiles,
        public array $quarantine,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'source_period_id' => $this->sourcePeriodId,
            'source_year' => $this->sourceYear,
            'target_year' => $this->targetYear,
            'target_period_id' => $this->targetPeriodId,
            'can_carry' => $this->canCarry,
            'checks' => $this->checks,
            'summary' => $this->summary,
            'sales_orders' => $this->salesOrders,
            'purchase_orders' => $this->purchaseOrders,
            'import_files' => $this->importFiles,
            'quarantine' => $this->quarantine,
        ];
    }
}
