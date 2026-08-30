<?php

namespace App\Modules\Commerce;

use DomainException;

final class ProviderRegistry
{
    /** @return array<string,array{label:string,status:string,capabilities:list<string>}> */
    public function all(): array
    {
        /** @var array<string,array{label:string,status:string,capabilities:list<string>}> $providers */
        $providers = config('commerce.providers', []);

        return $providers;
    }

    /** @return array{label:string,status:string,capabilities:list<string>} */
    public function get(string $provider): array
    {
        $provider = strtolower(trim($provider));
        $definition = $this->all()[$provider] ?? null;
        if (! is_array($definition)) {
            throw new DomainException('Unsupported commerce provider.');
        }

        return $definition;
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
