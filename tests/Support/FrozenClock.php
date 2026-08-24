<?php

namespace Tests\Support;

use App\Foundation\Clock\Clock;
use DateTimeImmutable;
use DateTimeZone;

final class FrozenClock implements Clock
{
    private DateTimeImmutable $instant;

    public function __construct(string|DateTimeImmutable $instant)
    {
        $this->instant = ($instant instanceof DateTimeImmutable ? $instant : new DateTimeImmutable($instant))
            ->setTimezone(new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->instant;
    }

    public function travelTo(string|DateTimeImmutable $instant): void
    {
        $this->instant = ($instant instanceof DateTimeImmutable ? $instant : new DateTimeImmutable($instant))
            ->setTimezone(new DateTimeZone('UTC'));
    }
}
