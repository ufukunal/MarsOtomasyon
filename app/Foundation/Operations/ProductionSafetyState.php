<?php

namespace App\Foundation\Operations;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use RuntimeException;
use Throwable;

final class ProductionSafetyState
{
    private bool $runtimeRecoveryMode = false;

    /** @param list<string> $disabledProviders */
    public function __construct(
        private bool $recoveryMode,
        private bool $outboundProvidersEnabled,
        private bool $asyncWorkEnabled,
        private bool $schedulerWorkEnabled,
        private int $retryAfterSeconds,
        private array $disabledProviders,
        private ?CacheRepository $store = null,
        private string $recoveryStateKey = 'mars:production:recovery-mode',
    ) {}

    public function recoveryMode(): bool
    {
        if ($this->recoveryMode || $this->runtimeRecoveryMode) {
            return true;
        }

        if ($this->store === null) {
            return false;
        }

        try {
            return (bool) $this->store->get($this->recoveryStateKey, false);
        } catch (Throwable) {
            // A shared recovery-state read failure must never reopen mutations.
            return true;
        }
    }

    public function enterRecoveryMode(): void
    {
        if ($this->store === null) {
            $this->runtimeRecoveryMode = true;

            return;
        }

        try {
            $this->store->forever($this->recoveryStateKey, true);
            // With a shared store, that store is authoritative so another process
            // can intentionally clear recovery mode for every instance.
            $this->runtimeRecoveryMode = false;
        } catch (Throwable $exception) {
            // Preserve a process-local fail-closed barrier when persistence fails.
            $this->runtimeRecoveryMode = true;

            throw new RuntimeException('Recovery mode could not be persisted to the shared safety store.', 0, $exception);
        }
    }

    public function leaveRecoveryMode(): void
    {
        if ($this->store !== null) {
            try {
                $this->store->forget($this->recoveryStateKey);
            } catch (Throwable $exception) {
                // Keep the process-local barrier active if the shared state cannot be cleared safely.
                $this->runtimeRecoveryMode = true;

                throw new RuntimeException('Recovery mode could not be cleared from the shared safety store.', 0, $exception);
            }
        }

        $this->runtimeRecoveryMode = false;
    }

    public function mutationsAllowed(): bool
    {
        return ! $this->recoveryMode();
    }

    public function outboundProvidersEnabled(): bool
    {
        return ! $this->recoveryMode() && $this->outboundProvidersEnabled;
    }

    public function asyncWorkEnabled(): bool
    {
        return ! $this->recoveryMode() && $this->asyncWorkEnabled;
    }

    public function schedulerWorkEnabled(): bool
    {
        return ! $this->recoveryMode() && $this->schedulerWorkEnabled;
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

    /** @return array{recovery_mode:bool,mutations_allowed:bool,outbound_providers_enabled:bool,async_work_enabled:bool,scheduler_work_enabled:bool,disabled_providers:list<string>} */
    public function snapshot(): array
    {
        return [
            'recovery_mode' => $this->recoveryMode(),
            'mutations_allowed' => $this->mutationsAllowed(),
            'outbound_providers_enabled' => $this->outboundProvidersEnabled(),
            'async_work_enabled' => $this->asyncWorkEnabled(),
            'scheduler_work_enabled' => $this->schedulerWorkEnabled(),
            'disabled_providers' => $this->disabledProviders,
        ];
    }
}
