<?php

namespace App\Modules\Dispatches\Shipping;

use DomainException;

final class ShippingProviderRegistry
{
    /** @var array<string, ShippingProviderGateway> */
    private array $providers = [];

    public function register(ShippingProviderGateway $gateway): self
    {
        $provider = strtolower(trim($gateway->provider()));
        if ($provider === '') {
            throw new DomainException('Shipping provider key is required.');
        }

        $this->providers[$provider] = $gateway;

        return $this;
    }

    public function get(string $provider): ShippingProviderGateway
    {
        $provider = strtolower(trim($provider));
        $gateway = $this->providers[$provider] ?? null;
        if (! $gateway instanceof ShippingProviderGateway) {
            throw new DomainException('Shipping provider is not registered.');
        }

        return $gateway;
    }

    public function supports(string $provider, string $capability): bool
    {
        return in_array($capability, $this->get($provider)->capabilities(), true);
    }
}
