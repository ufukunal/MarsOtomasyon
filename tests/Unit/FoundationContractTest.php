<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FoundationContractTest extends TestCase
{
    public function test_php_runtime_meets_the_locked_foundation_version(): void
    {
        self::assertTrue(
            version_compare(PHP_VERSION, '8.5.0', '>='),
            sprintf('MarsOtomasyon requires PHP 8.5+, running %s.', PHP_VERSION),
        );
    }
}
