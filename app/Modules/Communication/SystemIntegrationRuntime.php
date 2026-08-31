<?php

namespace App\Modules\Communication;

final readonly class SystemIntegrationRuntime
{
    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(
        public string $family,
        public ?string $providerKey,
        public bool $isEnabled,
        public string $verificationStatus,
        public ?string $endpointUrl,
        public array $settings,
        public array $credentials,
    ) {}

    public function isConfigurationValidated(): bool
    {
        return in_array($this->verificationStatus, ['configuration_validated', 'connection_tested'], true);
    }

    public function rateLimitPerMinute(): ?int
    {
        $value = $this->settings['rate_limit_per_minute'] ?? null;
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return null;
        }

        $limit = (int) $value;

        return $limit > 0 ? $limit : null;
    }
}
