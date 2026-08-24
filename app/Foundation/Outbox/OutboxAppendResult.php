<?php

namespace App\Foundation\Outbox;

final readonly class OutboxAppendResult
{
    public function __construct(
        public int $recordId,
        public string $eventId,
        public bool $isNew,
    ) {}

    public function isReplay(): bool
    {
        return ! $this->isNew;
    }
}
