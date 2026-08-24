<?php

namespace Tests\Unit;

use App\Foundation\Providers\CapabilityKey;
use App\Foundation\Providers\ProviderFamily;
use App\Foundation\Providers\ProviderKey;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ProviderNamingContractTest extends TestCase
{
    public function test_provider_and_capability_keys_are_canonical(): void
    {
        self::assertSame('amazon-sp-api', (string) new ProviderKey('amazon-sp-api'));
        self::assertSame('shipment.create', (string) new CapabilityKey('shipment.create'));
        self::assertSame('shipping', ProviderFamily::Shipping->value);
    }

    public function test_non_canonical_provider_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ProviderKey('Amazon SP API');
    }

    public function test_non_canonical_capability_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CapabilityKey('Shipment Create');
    }
}
