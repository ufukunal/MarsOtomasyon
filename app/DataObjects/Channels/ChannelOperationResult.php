<?php

namespace App\DataObjects\Channels;

final readonly class ChannelOperationResult
{
    /** @param array<string, scalar|null> $safeMetadata */
    public function __construct(
        public bool $success,
        public ?string $externalId = null,
        public ?string $message = null,
        public array $safeMetadata = [],
    ) {}
}
