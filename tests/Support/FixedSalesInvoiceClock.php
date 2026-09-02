<?php

namespace Tests\Support;

use App\Foundation\Clock\Clock;
use DateTimeImmutable;

trait FixedSalesInvoiceClock
{
    protected function setUpFixedSalesInvoiceClock(): void
    {
        $this->app->instance(Clock::class, new class implements Clock
        {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-08-27T12:00:00+00:00');
            }
        });
    }
}
