<?php

namespace Tests\Unit;

use App\Foundation\Outbox\OutboxEventCatalog;
use App\Foundation\Outbox\OutboxRetryCapability;
use App\Foundation\Outbox\OutboxSemantic;
use App\Foundation\Outbox\OutboxUnknownEvent;
use PHPUnit\Framework\TestCase;

final class OutboxEventCatalogTest extends TestCase
{
    public function test_m0_catalog_contains_only_the_foundation_smoke_event(): void
    {
        $catalog = new OutboxEventCatalog;
        $definition = $catalog->definition(OutboxEventCatalog::SYSTEM_SMOKE_V1);

        self::assertSame([OutboxEventCatalog::SYSTEM_SMOKE_V1], $catalog->names());
        self::assertSame(1, $definition->schemaVersion);
        self::assertSame(OutboxSemantic::ImmutableEventSnapshot, $definition->semantic);
        self::assertSame(OutboxRetryCapability::SafeRetry, $definition->retryCapability);
    }

    public function test_unregistered_business_event_is_rejected(): void
    {
        $this->expectException(OutboxUnknownEvent::class);

        (new OutboxEventCatalog)->definition('sales.invoice.posted.v1');
    }
}
