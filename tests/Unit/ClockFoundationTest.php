<?php

namespace Tests\Unit;

use App\Foundation\Clock\Clock;
use App\Foundation\Clock\SystemClock;
use DateTimeImmutable;
use Tests\Support\FrozenClock;
use Tests\TestCase;

final class ClockFoundationTest extends TestCase
{
    public function test_system_clock_is_the_default_clock_binding_and_uses_utc(): void
    {
        $clock = $this->app->make(Clock::class);

        self::assertInstanceOf(SystemClock::class, $clock);
        self::assertSame('UTC', $clock->now()->getTimezone()->getName());
    }

    public function test_frozen_clock_is_deterministic(): void
    {
        $clock = new FrozenClock('2026-08-24T12:00:00+03:00');

        self::assertEquals(new DateTimeImmutable('2026-08-24T09:00:00Z'), $clock->now());

        $clock->travelTo('2026-08-25T00:00:00Z');
        self::assertEquals(new DateTimeImmutable('2026-08-25T00:00:00Z'), $clock->now());
    }
}
