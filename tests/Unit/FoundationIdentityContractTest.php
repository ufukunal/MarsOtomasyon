<?php

namespace Tests\Unit;

use App\Foundation\Identity\ExternalIdentity;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Foundation\Providers\ProviderFamily;
use App\Foundation\Providers\ProviderKey;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FoundationIdentityContractTest extends TestCase
{
    public function test_external_identity_is_stable_and_all_dimensions_participate_in_identity(): void
    {
        $identity = new ExternalIdentity(
            companyId: 42,
            providerFamily: ProviderFamily::Marketplace,
            provider: new ProviderKey('trendyol'),
            connectionKey: 'main-store',
            entityType: 'order',
            externalId: 'ORD/2026/0001',
        );

        $same = new ExternalIdentity(
            companyId: 42,
            providerFamily: ProviderFamily::Marketplace,
            provider: new ProviderKey('trendyol'),
            connectionKey: 'main-store',
            entityType: 'order',
            externalId: 'ORD/2026/0001',
        );

        $otherConnection = new ExternalIdentity(
            companyId: 42,
            providerFamily: ProviderFamily::Marketplace,
            provider: new ProviderKey('trendyol'),
            connectionKey: 'outlet-store',
            entityType: 'order',
            externalId: 'ORD/2026/0001',
        );

        self::assertSame($identity->components(), [
            'company_id' => 42,
            'provider_family' => 'marketplace',
            'provider' => 'trendyol',
            'connection_key' => 'main-store',
            'entity_type' => 'order',
            'external_id' => 'ORD/2026/0001',
        ]);
        self::assertSame($identity->fingerprint(), $same->fingerprint());
        self::assertNotSame($identity->fingerprint(), $otherConnection->fingerprint());
    }

    public function test_external_identity_rejects_non_canonical_connection_or_entity_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExternalIdentity(
            companyId: 42,
            providerFamily: ProviderFamily::Shipping,
            provider: new ProviderKey('carrier-x'),
            connectionKey: 'Main Store',
            entityType: 'shipment',
            externalId: '123',
        );
    }

    public function test_source_effect_identity_is_stable_and_effect_specific(): void
    {
        $stockOut = new SourceEffectIdentity(
            companyId: 42,
            sourceType: 'sales.dispatch',
            sourceId: '981',
            effectType: 'stock.out',
        );
        $same = new SourceEffectIdentity(
            companyId: 42,
            sourceType: 'sales.dispatch',
            sourceId: '981',
            effectType: 'stock.out',
        );
        $accountEffect = new SourceEffectIdentity(
            companyId: 42,
            sourceType: 'sales.dispatch',
            sourceId: '981',
            effectType: 'account.debit',
        );

        self::assertSame($stockOut->fingerprint(), $same->fingerprint());
        self::assertNotSame($stockOut->fingerprint(), $accountEffect->fingerprint());
    }

    public function test_source_effect_identity_rejects_ambiguous_padded_source_ids(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SourceEffectIdentity(
            companyId: 42,
            sourceType: 'sales.invoice',
            sourceId: ' 1001 ',
            effectType: 'account.debit',
        );
    }
}
