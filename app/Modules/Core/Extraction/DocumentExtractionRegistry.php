<?php

namespace App\Modules\Core\Extraction;

use DomainException;

final class DocumentExtractionRegistry
{
    /** @var array<string, DocumentExtractionProvider> */
    private array $providers = [];

    public function register(DocumentExtractionProvider $provider): self
    {
        $key = strtolower(trim($provider->provider()));
        if ($key === '') {
            throw new DomainException('Document extraction provider key is required.');
        }
        $this->providers[$key] = $provider;

        return $this;
    }

    public function get(string $provider): DocumentExtractionProvider
    {
        $provider = strtolower(trim($provider));
        $implementation = $this->providers[$provider] ?? null;
        if (! $implementation instanceof DocumentExtractionProvider) {
            throw new DomainException('Document extraction provider is not registered.');
        }

        return $implementation;
    }
}
