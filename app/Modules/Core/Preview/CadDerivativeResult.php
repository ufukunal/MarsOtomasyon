<?php

namespace App\Modules\Core\Preview;

use DateTimeImmutable;

final readonly class CadDerivativeResult
{
    /**
     * @param array<string, mixed> $manifest
     */
    public function __construct(
        public string $status,
        public ?string $providerJobId,
        public ?string $previewKind,
        public array $manifest,
        public ?string $derivativeSha256,
        public ?DateTimeImmutable $expiresAt,
    ) {
        // Constructor property promotion only.
    }
}
