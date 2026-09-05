<?php

namespace App\Modules\Commerce;

use DomainException;

final class ProviderRegistry
{
    /** @return array<string,array{label:string,status:string,contract_version:string,deprecated_after:?string,capabilities:list<string>}> */
    public function all(): array
    {
        /** @var array<string,array{label:string,status:string,contract_version:string,deprecated_after:?string,capabilities:list<string>}> $providers */
        $providers = config('commerce.providers', []);

        return $providers;
    }

    /** @return array{label:string,status:string,contract_version:string,deprecated_after:?string,capabilities:list<string>} */
    public function get(string $provider): array
    {
        $provider = strtolower(trim($provider));
        $definition = $this->all()[$provider] ?? null;
        if (! is_array($definition)) {
            throw new DomainException('Unsupported commerce provider.');
        }

        return $definition;
    }

    /** @return array{contract_version:string,deprecated_after:?string} */
    public function lifecycle(string $provider): array
    {
        $definition = $this->get($provider);

        return [
            'contract_version' => $definition['contract_version'],
            'deprecated_after' => $definition['deprecated_after'],
        ];
    }

    public function supports(string $provider, string $capability): bool
    {
        $definition = $this->get($provider);

        return in_array($capability, $definition['capabilities'], true);
    }

    public function isContractVerified(string $provider): bool
    {
        return in_array($this->get($provider)['status'], ['contract_verified', 'verified_marketplace'], true);
    }

    public function isMarketplaceVerified(string $provider): bool
    {
        return $this->get($provider)['status'] === 'verified_marketplace';
    }
}
