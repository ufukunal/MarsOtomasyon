<?php

namespace Tests\Unit;

use App\Foundation\Clock\SystemClock;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    public function test_system_clock_always_returns_utc_time(): void
    {
        $now = (new SystemClock)->now();

        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertSame('+00:00', $now->format('P'));
    }
}
