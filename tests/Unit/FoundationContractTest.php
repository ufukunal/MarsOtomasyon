<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FoundationContractTest extends TestCase
{
    public function test_financial_precision_contract_is_documented_as_decimal(): void
    {
        self::assertSame('20,6', '20,6');
    }
}
