<?php

namespace Tests\Unit;

use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Identity\TaxIdentity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AccountTaxIdentityTest extends TestCase
{
    public function test_validates_turkish_tckn_checksum_without_external_lookup(): void
    {
        self::assertTrue(TaxIdentity::validTckn('10000000146'));
        self::assertSame('10000000146', TaxIdentity::normalize(TaxIdentityType::Tckn, ' 10000000146 '));
        self::assertFalse(TaxIdentity::validTckn('10000000145'));
    }

    public function test_validates_turkish_vkn_checksum_without_external_lookup(): void
    {
        self::assertTrue(TaxIdentity::validVkn('1234567890'));
        self::assertSame('1234567890', TaxIdentity::normalize(TaxIdentityType::Vkn, '1234567890'));
        self::assertFalse(TaxIdentity::validVkn('1234567891'));
    }

    public function test_none_requires_an_empty_identity(): void
    {
        self::assertNull(TaxIdentity::normalize(TaxIdentityType::None, null));
        self::assertNull(TaxIdentity::normalize(TaxIdentityType::None, ''));

        $this->expectException(InvalidArgumentException::class);
        TaxIdentity::normalize(TaxIdentityType::None, '123');
    }

    public function test_foreign_identity_is_trimmed_uppercase_and_bounded(): void
    {
        self::assertSame('DE 123-ABC', TaxIdentity::normalize(TaxIdentityType::Foreign, ' de 123-abc '));
    }

    public function test_rejects_invalid_checksum_in_normalization(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TaxIdentity::normalize(TaxIdentityType::Tckn, '10000000145');
    }
}
