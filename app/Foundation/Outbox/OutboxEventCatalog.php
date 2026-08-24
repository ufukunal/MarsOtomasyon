<?php

namespace App\Foundation\Outbox;

final class OutboxEventCatalog
{
    public const SYSTEM_SMOKE_V1 = 'system.smoke.v1';

    public function definition(string $eventName): OutboxEventDefinition
    {
        return match ($eventName) {
            self::SYSTEM_SMOKE_V1 => new OutboxEventDefinition(
                name: self::SYSTEM_SMOKE_V1,
                schemaVersion: 1,
                semantic: OutboxSemantic::ImmutableEventSnapshot,
                retryCapability: OutboxRetryCapability::SafeRetry,
                allowedPayloadKeys: ['source', 'sequence'],
                requiredPayloadKeys: ['source', 'sequence'],
            ),
            default => throw new OutboxUnknownEvent('Outbox event is not registered in the internal event catalog.'),
        };
    }

    /** @return list<string> */
    public function names(): array
    {
        return [self::SYSTEM_SMOKE_V1];
    }
}
