<?php

namespace App\Foundation\Identity;

use App\Foundation\Providers\ProviderFamily;
use App\Foundation\Providers\ProviderKey;
use InvalidArgumentException;
use JsonException;
use Stringable;

final readonly class ExternalIdentity implements Stringable
{
    public function __construct(
        public int $companyId,
        public ProviderFamily $providerFamily,
        public ProviderKey $provider,
        public string $connectionKey,
        public string $entityType,
        public string $externalId,
    ) {
        if ($companyId < 1) {
            throw new InvalidArgumentException('External identity requires a persisted company id.');
        }

        self::assertCanonicalKey($connectionKey, 'Connection key');
        self::assertCanonicalKey($entityType, 'Entity type');

        if ($externalId === '' || $externalId !== trim($externalId) || mb_strlen($externalId) > 512) {
            throw new InvalidArgumentException('External id must be non-empty, unpadded and at most 512 characters.');
        }
    }

    /** @return array{company_id:int,provider_family:string,provider:string,connection_key:string,entity_type:string,external_id:string} */
    public function components(): array
    {
        return [
            'company_id' => $this->companyId,
            'provider_family' => $this->providerFamily->value,
            'provider' => (string) $this->provider,
            'connection_key' => $this->connectionKey,
            'entity_type' => $this->entityType,
            'external_id' => $this->externalId,
        ];
    }

    /**
     * Stable opaque identity suitable for idempotency/mapping keys.
     *
     * @throws JsonException
     */
    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->components(), JSON_THROW_ON_ERROR));
    }

    /** @throws JsonException */
    public function __toString(): string
    {
        return $this->fingerprint();
    }

    private static function assertCanonicalKey(string $value, string $label): void
    {
        if (! preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $value)) {
            throw new InvalidArgumentException($label.' must be canonical lowercase ASCII.');
        }
    }
}
