<?php

namespace App\Modules\Core\Preview;

use DomainException;

final class CadDerivativeProviderRegistry
{
    /** @var array<string, CadDerivativeProvider> */
    private array $providers = [];

    public function register(CadDerivativeProvider $provider): self
    {
        $key = strtolower(trim($provider->provider()));
        if ($key === '') {
            throw new DomainException('CAD derivative provider key is required.');
        }

        $this->providers[$key] = $provider;

        return $this;
    }

    public function get(string $provider): CadDerivativeProvider
    {
        $key = strtolower(trim($provider));
        $implementation = $this->providers[$key] ?? null;
        if (! $implementation instanceof CadDerivativeProvider) {
            throw new DomainException('CAD derivative provider is not registered.');
        }

        return $implementation;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->providers);
    }
}
