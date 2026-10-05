<?php

namespace App\DataObjects\Channels;

final readonly class ChannelExternalEventReservation
{
    public function __construct(
        public int $registryId,
        public bool $reserved,
        public string $status,
        public ?int $periodId = null,
        public ?int $periodDocumentId = null,
    ) {}
}
