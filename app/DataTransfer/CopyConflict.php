<?php

namespace App\DataTransfer;

final readonly class CopyConflict
{
    public function __construct(
        public int $sourceId,
        public string $sourceCode,
        public string $sourceLabel,
        public int $existingTargetId,
        public string $existingTargetLabel,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'source_id' => $this->sourceId,
            'source_code' => $this->sourceCode,
            'source_label' => $this->sourceLabel,
            'existing_target_id' => $this->existingTargetId,
            'existing_target_label' => $this->existingTargetLabel,
        ];
    }
}
