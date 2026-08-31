<?php

namespace App\Modules\Communication;

final readonly class SystemIntegrationSummary
{
    public function __construct(
        public string $family,
        public ?string $providerKey,
        public bool $isEnabled,
        public string $verificationStatus,
        public ?string $endpointUrl,
        public ?string $settings,
        public bool $hasCredentials,
        public ?string $lastValidatedAt,
        public ?string $lastValidationError,
    ) {}
}
