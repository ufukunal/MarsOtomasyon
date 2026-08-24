<?php

namespace App\Foundation\Idempotency;

final readonly class IdempotencyClaim
{
    public function __construct(
        public int $recordId,
        public bool $isNew,
        public IdempotencyStatus $status,
    ) {
    }

    public function isReplay(): bool
    {
        return ! $this->isNew;
    }
}
