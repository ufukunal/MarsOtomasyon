<?php

namespace App\DataObjects\Channels;

use Carbon\CarbonImmutable;

final readonly class ChannelInboundEvent
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $eventType,
        public string $externalId,
        public CarbonImmutable $occurredAt,
        public array $data,
    ) {}
}
