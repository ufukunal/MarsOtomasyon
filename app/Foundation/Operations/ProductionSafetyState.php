<?php

namespace App\Foundation\Operations;

use RuntimeException;

final readonly class ProductionSafetyState
{
    /** @param list<string> $disabledProviders */
    public function __construct(
        private bool $recoveryMode,
        private bool $outboundProvidersEnabled,
        private bool $asyncWorkEnabled,
        private bool $schedulerWorkEnabled,
        private int $retryAfterSeconds,
        private array $disabledProviders,
    ) {}

    public function recoveryMode(): bool
    {
        return $this->recoveryMode;
    }

    public function outboundProvidersEnabled(): bool
    {
        return ! $this->recoveryMode && $this->outboundProvidersEnabled;
    }

    public function asyncWorkEnabled(): bool
    {
        return ! $this->recoveryMode && $this->asyncWorkEnabled;
    }

    public function schedulerWorkEnabled(): bool
    {
        return ! $this->recoveryMode && $this->schedulerWorkEnabled;
    }

    public function providerEnabled(string $provider): bool
    {
        $provider = strtolower(trim($provider));

        return $provider !== ''
            && $this->outboundProvidersEnabled()
            && ! in_array($provider, $this->disabledProviders, true);
    }

    public function retryAfterSeconds(): int
    {
        return max(30, $this->retryAfterSeconds);
    }

    public function assertProviderEnabled(string $provider): void
    {
        if (! $this->providerEnabled($provider)) {
            throw new RuntimeException('Outbound provider is disabled by production safety controls: '.strtolower(trim($provider)));
        }
    }

    /** @return array{recovery_mode:bool,outbound_providers_enabled:bool,async_work_enabled:bool,scheduler_work_enabled:bool,disabled_providers:list<string>} */
    public function snapshot(): array
    {
        return [
            'recovery_mode' => $this->recoveryMode(),
            'outbound_providers_enabled' => $this->outboundProvidersEnabled(),
            'async_work_enabled' => $this->asyncWorkEnabled(),
            'scheduler_work_enabled' => $this->schedulerWorkEnabled(),
            'disabled_providers' => $this->disabledProviders,
        ];
    }
}
