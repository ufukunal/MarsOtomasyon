<?php

namespace App\DataObjects\Periods;

final readonly class CarryResult
{
    /** @param array<string,mixed> $details */
    public function __construct(
        public int $sourcePeriodId,
        public int $targetPeriodId,
        public int $sourceYear,
        public int $targetYear,
        public array $details,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'source_period_id' => $this->sourcePeriodId,
            'target_period_id' => $this->targetPeriodId,
            'source_year' => $this->sourceYear,
            'target_year' => $this->targetYear,
            'details' => $this->details,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            sourcePeriodId: (int) $data['source_period_id'],
            targetPeriodId: (int) $data['target_period_id'],
            sourceYear: (int) $data['source_year'],
            targetYear: (int) $data['target_year'],
            details: is_array($data['details'] ?? null) ? $data['details'] : [],
        );
    }
}
